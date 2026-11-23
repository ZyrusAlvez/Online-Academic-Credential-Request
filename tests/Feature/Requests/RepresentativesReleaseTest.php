<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\DocumentKind;
use App\Enums\RecipientType;
use App\Enums\RequesterMode;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

afterEach(fn () => Carbon::setTestNow());

/** A free request taken all the way to ready for release. */
function readyRequest(): CredentialRequest
{
    $request = acceptRequest(submitRequest(test()->owner, test()->org->free), test()->registrar);
    passOffice($request, test()->registrar);   // prepare
    passOffice($request, test()->registrar);   // final check

    return $request->fresh();
}

test('REQ-11-01 the owner declares a representative with SPA and ID when submitting', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->free->id)
        ->set('form.purpose', 'Employment')
        ->set('form.requester_mode', 'representative')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        // Review merges every section's errors, so they are checked by field and message.
        ->assertHasErrors(['form.representative_name', 'spa', 'representativeId'])
        ->assertSee('Upload the SPA.')->assertSee('Upload the representative&#039;s valid ID.', escape: false)
        ->set('form.representative_name', 'Rosa Dela Cruz')
        ->set('form.representative_relationship', 'Mother')
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
        ->call('review')->assertHasErrors('representativeId')->assertHasNoErrors('spa')
        ->assertSet('reviewing', false)
        ->set('representativeId', UploadedFile::fake()->image('id.jpg'))
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    $request = CredentialRequest::sole();
    $representative = $request->representatives()->sole();

    expect($request->requester_mode)->toBe(RequesterMode::Representative)
        ->and($representative)->full_name->toBe('Rosa Dela Cruz')->relationship->toBe('Mother')->status->toBe(ReviewStatus::Pending)
        ->and($request->documents()->pluck('kind')->all())->toBe([DocumentKind::Spa, DocumentKind::RepresentativeId]);

    $request->documents->each(fn ($document) => Storage::disk(RequestWorkflow::proofDisk())->assertExists($document->path));
});

/** A TOR request the evaluator sent to the office for an original (REQ-20-04). */
function officeVisitRequest(): CredentialRequest
{
    $request = submitRequest(test()->owner, test()->org->tor);
    workflow()->startEvaluation($request, test()->registrar);
    workflow()->recordCheck($request, test()->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->requireOfficeVisit($request, test()->registrar, ['PSA-authenticated birth certificate'], 'Bring the original.');

    return $request->fresh();
}

test('REQ-11-01 the workflow takes no representative without both documents', function () {
    $parameters = (new ReflectionMethod(RequestWorkflow::class, 'declareRepresentative'))->getParameters();

    foreach ([3 => 'spa', 4 => 'idFile'] as $position => $name) {
        expect($parameters[$position]->getName())->toBe($name)
            ->and($parameters[$position]->allowsNull())->toBeFalse()
            ->and($parameters[$position]->isOptional())->toBeFalse();
    }
});

test('REQ-11-09 a wrong file type or a file over 5 MB is reported as soon as it is chosen', function () {
    $create = Livewire::actingAs($this->owner)->test(Create::class)->set('form.requester_mode', 'representative');

    $create->set('spa', UploadedFile::fake()->create('spa.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertHasErrors(['spa' => 'mimes'])->assertSee('The SPA must be a JPG, PNG, WebP or PDF file.')
        ->set('representativeId', UploadedFile::fake()->create('id.pdf', 5121, 'application/pdf'))
        ->assertHasErrors(['representativeId'])->assertSee('must be 5 MB or smaller.')
        ->set('representativeId', UploadedFile::fake()->create('id.pdf', 5120, 'application/pdf'))
        ->assertHasNoErrors('representativeId');

    // Too big even for Livewire's temporary upload: the same plain words, not "12288 kilobytes".
    $create->set('spa', UploadedFile::fake()->create('spa.pdf', 13000, 'application/pdf'))
        ->assertHasErrors('spa')->assertSee('The file must be 5 MB or smaller.')->assertDontSee('kilobytes');
});

test('REQ-11-03 the registrar verifies or rejects a representative', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    $verified = declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);
    $rejected = declareRepresentative($request, $this->owner, ['full_name' => 'Juan Cruz']);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->call('verifyRepresentative', $verified->id)
        ->call('confirmRejectRepresentative', $rejected->id)
        ->call('rejectRepresentative')->assertHasErrors('representativeRemarks')
        ->set('representativeRemarks', 'SPA not notarized')
        ->call('rejectRepresentative')->assertHasNoErrors();

    expect($verified->fresh())->status->toBe(ReviewStatus::Verified)->reviewed_by->toBe($this->registrar->id)
        ->and($rejected->fresh())->status->toBe(ReviewStatus::Rejected)->remarks->toBe('SPA not notarized');
});

test('REQ-11-04 only the owner and registrars can open representative documents', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    declareRepresentative($request, $this->owner, ['full_name' => 'Pedro']);
    $document = $request->documents()->where('kind', DocumentKind::Spa)->sole();

    $this->actingAs($this->owner)->get(route('request-documents.show', $document))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->registrar)->get(route('request-documents.show', $document))->assertOk();
    $this->actingAs(requester($this->org->cba))->get(route('request-documents.show', $document))->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff($this->org->vpf)->create())->get(route('request-documents.show', $document))->assertNotFound();
});

test('REQ-11-05 a representative is mentioned only when one was declared and not rejected', function () {
    $readyMail = function (CredentialRequest $request): string {
        $event = $request->events()->with(['department', 'step'])->get()->firstWhere('type', RequestEventType::ReadyForRelease);

        return implode(' ', (new RequestActivity($event, 'Ready'))->toMail($this->owner)->introLines);
    };
    $claimText = fn (CredentialRequest $request) => Str::of($this->actingAs($this->owner)->get(route('student.requests.show', $request))->getContent())
        ->after('data-claim-who')->before('</p>')->squish()->toString();
    $visitText = fn (CredentialRequest $request) => Str::of($this->actingAs($this->owner)->get(route('student.requests.show', $request))->getContent())
        ->after('data-office-visit-who')->before('</p>')->squish()->toString();

    // Nobody declared: in person only, on the page and in the email.
    $ready = readyRequest();
    $visit = officeVisitRequest();
    expect($claimText($ready))->toContain('Claim it at the Office of the Registrar with a valid ID.')->not->toContain('representative')->not->toContain('SPA')
        ->and($visitText($visit))->toContain('Come in person with a valid ID.')->not->toContain('representative')
        ->and($readyMail($ready))->not->toContain('representative')->not->toContain('Special Power of Attorney');

    // Declared (here through the workflow, as New Request does): named everywhere.
    $rosa = declareRepresentative($ready, $this->owner, ['full_name' => 'Rosa Reyes']);
    declareRepresentative($visit, $this->owner, ['full_name' => 'Rosa Reyes']);
    expect($claimText($ready))->toContain('or send Rosa Reyes, your representative, with the SPA')
        ->and($visitText($visit))->toContain('or send Rosa Reyes, your representative, with the Special Power of Attorney (SPA)')
        ->and($readyMail($ready))->toContain('or send Rosa Reyes, your representative');

    // Rejected: back to in person only.
    workflow()->reviewRepresentative($rosa, $this->registrar, false, 'SPA unsigned');
    expect($claimText($ready))->not->toContain('Rosa Reyes')
        ->and($readyMail($ready->fresh()))->not->toContain('Rosa Reyes');
});

test('REQ-11-07 no release to an unverified representative; a verified one can claim', function () {
    $request = readyRequest();
    $representative = declareRepresentative($request, $this->owner, ['full_name' => 'Rosa Dela Cruz']);

    expect(fn () => workflow()->release($request, $this->registrar, RecipientType::Representative, $representative->id))
        ->toThrow(InvalidTransition::class, 'must be verified')
        ->and($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);

    workflow()->reviewRepresentative($representative, $this->registrar, true);
    workflow()->release($request, $this->registrar, RecipientType::Representative, $representative->id);

    expect($request->release()->sole())->request_representative_id->toBe($representative->id)->recipient_name->toBe('Rosa Dela Cruz');

    // Not declared: checked at the counter, released under the name typed in (REQ-16-28).
    $other = readyRequest();
    expect(fn () => workflow()->release($other, $this->registrar, RecipientType::Representative))
        ->toThrow(InvalidTransition::class, "Enter the representative's full name.");

    workflow()->release($other, $this->registrar, RecipientType::Representative, representativeName: ' Pedro Santos ');
    expect($other->release()->sole())->request_representative_id->toBeNull()->recipient_name->toBe('Pedro Santos')
        ->recipient_type->toBe(RecipientType::Representative);
});

test('REQ-11-08 the processing time in working days is stored when the credential is ready for release', function () {
    Carbon::setTestNow('2026-10-09 10:00:00');   // Friday
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);
    passOffice($request, $this->registrar);   // prepare

    Carbon::setTestNow('2026-10-14 15:00:00');   // Wednesday
    passOffice($request, $this->registrar);   // final check: ready for release

    expect($request->fresh())->status->toBe(RequestStatus::ReadyForRelease)
        ->processing_working_days->toBe(4);   // Sat, Mon, Tue, Wed

    // Claimed a week later: the claim date does not count (REQ-34-11).
    Carbon::setTestNow('2026-10-21 09:00:00');
    workflow()->release($request->fresh(), $this->registrar, RecipientType::Owner);

    expect($request->fresh()->processing_working_days)->toBe(4);
});
