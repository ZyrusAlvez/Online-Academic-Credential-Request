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
use App\Livewire\Student\Requests\Show as RequesterShow;
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

test('REQ-11-01 after submitting, the request page never offers to add a representative', function () {
    $submitted = submitRequest($this->owner, $this->org->tor);
    $requests = [$submitted, officeVisitRequest(), readyRequest()];

    expect(array_map(fn (CredentialRequest $r) => $r->fresh()->status, $requests))
        ->toBe([RequestStatus::Submitted, RequestStatus::PendingCompliance, RequestStatus::ReadyForRelease]);

    foreach ($requests as $request) {
        expect($this->owner->can('declareRepresentative', $request->fresh()))->toBeFalse();

        $this->actingAs($this->owner)->get(route('student.requests.show', $request))
            ->assertOk()
            ->assertDontSee('Authorized Representative')
            ->assertDontSee('Add representative')
            ->assertDontSeeHtml('declare-representative')
            ->assertDontSeeHtml('wire:model="spa"');
    }

    expect(method_exists(RequesterShow::class, 'declareRepresentative'))->toBeFalse();
});

test('REQ-11-01 a representative declared earlier stays listed, without the Add button', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertSee('Authorized Representative')
        ->assertSee('Pedro Santos')
        ->assertDontSee('Add representative');
});

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

test('REQ-11-10 the SPA and representative\'s ID use the valid ID\'s picker, its name a link to the viewer', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->set('form.requester_mode', 'representative')->html();

    foreach (['spa' => 'data-spa-picker', 'representativeId' => 'data-representative-id-picker'] as $model => $marker) {
        $field = Str::after($html, $marker);

        expect($field)->toMatch('/^[^>]*x-data="\{ chosen: null/')
            ->toMatch('/<input type="file" wire:model="'.$model.'"[^>]*class="sr-only"/')
            ->toContain('Choose file')->toContain('No file chosen')
            ->toMatch('/<a href="#"[^>]*data-chosen-file[^>]*x-text="chosen\?\.name"\s*x-on:click\.prevent="\$dispatch\(\'open-file\', chosen\)"/')
            ->toMatch('/<div x-show="uploading"[^>]*role="status"[^>]*data-upload-progress>/');
    }

    // Only the picker's own input: the browser's picker with its repeated name is gone.
    expect(substr_count($html, 'wire:model="spa"'))->toBe(1)
        ->and(substr_count($html, 'wire:model="representativeId"'))->toBe(1);
});

test('REQ-11-11 choosing who will transact shows or hides the representative\'s fields in the browser, without a round trip', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();

    // The choice is not sent to the server; the fields are on the page, hidden until chosen.
    expect($html)->toContain('wire:model="form.requester_mode"')->not->toContain('wire:model.live="form.requester_mode"')
        ->toMatch('/<div class="space-y-6" data-representative-fields\s+x-show="\$wire\.form\.requester_mode === \'representative\'"\s+x-cloak style="display: none"\s*>/')
        ->and(Str::after($html, 'data-representative-fields'))->toContain('wire:model.live.blur="form.representative_name"')->toContain('data-spa-picker');

    // Coming back to the form with a representative chosen, the fields show from the start.
    $chosen = Livewire::actingAs($this->owner)->test(Create::class)->set('form.requester_mode', 'representative')->html();
    expect($chosen)->toMatch('/data-representative-fields\s+x-show="[^"]*"\s*>/');
});

test('REQ-11-01 a representative\'s contact number, when given, must be exactly 11 digits', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.requester_mode', 'representative')
        ->set('form.representative_contact', '0918 555 0101')
        ->call('review')
        ->assertHasErrors(['form.representative_contact'])
        ->set('form.representative_contact', '09185550101')
        ->call('review')
        ->assertHasNoErrors('form.representative_contact');
});

test('REQ-11-02 the representative is recorded apart from the owner and shown with their status', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    $representative = declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);

    expect(User::where('first_name', 'Pedro')->exists())->toBeFalse();

    // The owner sees no "For verification" (2026-11-13); the registrar still does, to act on it.
    $ownerPage = fn (): string => Str::after($this->actingAs($this->owner)->get(route('student.requests.show', $request))->getContent(), 'id="representatives-heading"');
    expect($ownerPage())->toContain('Pedro Santos')->not->toContain('For verification');
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $request))->assertSee('Pedro Santos')->assertSee('For verification');

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->call('verifyRepresentative', $representative->id);
    expect(Str::before($ownerPage(), 'id="details-heading"'))->toContain('Verified');
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

test('REQ-11-05 the requester is told the credential is ready and what to bring', function () {
    $request = readyRequest();

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertSee('Your Credential Is Ready for Release')
        ->assertSee('with a valid ID');
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
});

test('REQ-11-08 the processing time in working days is stored at release', function () {
    Carbon::setTestNow('2026-10-09 10:00:00');   // Friday
    $request = readyRequest();

    Carbon::setTestNow('2026-10-14 15:00:00');   // Wednesday
    workflow()->release($request, $this->registrar, RecipientType::Owner);

    expect($request->fresh()->processing_working_days)->toBe(4);   // Sat, Mon, Tue, Wed
});
