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

test('REQ-11-01 after submitting, a representative can be added only while an office visit is required', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    // Submitted, then under evaluation: no Add representative, and the server refuses it.
    foreach ([fn () => null, fn () => workflow()->startEvaluation($request, $this->registrar)] as $step) {
        $step();

        $this->actingAs($this->owner)->get(route('student.requests.show', $request))
            ->assertOk()
            ->assertDontSee('Authorized Representative')
            ->assertDontSee('Add representative');

        Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
            ->set('representativeName', 'Pedro Santos')
            ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
            ->set('representativeId', UploadedFile::fake()->image('id.jpg'))
            ->call('declareRepresentative')
            ->assertForbidden();
    }

    // Sent to the office: now the owner may send a representative.
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->requireOfficeVisit($request, $this->registrar, ['PSA-authenticated birth certificate'], 'Bring the original.');

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertSee('Add representative');
    expect($request->representatives()->count())->toBe(0);
});

test('REQ-11-01 once the credential is ready for release the owner can add someone to claim it, but not while it is processed', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);

    // Accepted and being processed by the offices: still no Add representative.
    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertOk()->assertDontSee('Add representative');

    passOffice($request, $this->registrar);   // prepare
    passOffice($request, $this->registrar);   // final check
    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertSee('Add representative');

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request->fresh()])
        ->set('representativeName', 'Pedro Santos')
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
        ->set('representativeId', UploadedFile::fake()->image('id.jpg'))
        ->call('declareRepresentative')
        ->assertHasNoErrors();

    expect($request->representatives()->sole())->full_name->toBe('Pedro Santos')->status->toBe(ReviewStatus::Pending);
});

test('REQ-11-01 a representative declared earlier stays listed, without the Add button', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertSee('Authorized Representative')
        ->assertSee('Pedro Santos')
        ->assertDontSee('Add representative');
});

test('REQ-11-01 a representative added later needs the SPA and their valid ID', function () {
    $request = officeVisitRequest();

    $page = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->assertSeeHtml('wire:model="spa"')->assertSeeHtml('wire:model="representativeId"')
        ->assertSee('JPG, PNG, WebP or PDF, up to 5 MB each')
        ->set('representativeName', 'Pedro Santos')
        ->call('declareRepresentative')
        ->assertHasErrors(['spa' => 'required', 'representativeId' => 'required'])
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
        ->call('declareRepresentative')
        ->assertHasErrors(['representativeId' => 'required']);

    expect($request->representatives()->count())->toBe(0)
        ->and($request->documents()->count())->toBe(0);

    $page->set('representativeId', UploadedFile::fake()->image('id.jpg'))
        ->call('declareRepresentative')
        ->assertHasNoErrors();

    expect($request->representatives()->sole()->full_name)->toBe('Pedro Santos')
        ->and($request->documents()->pluck('kind')->all())->toBe([DocumentKind::Spa, DocumentKind::RepresentativeId])
        ->and($request->events()->get()->last()->type)->toBe(RequestEventType::RepresentativeDeclared);
});

test('REQ-11-01 the workflow takes no representative without both documents', function () {
    $parameters = (new ReflectionMethod(RequestWorkflow::class, 'declareRepresentative'))->getParameters();

    foreach ([3 => 'spa', 4 => 'idFile'] as $position => $name) {
        expect($parameters[$position]->getName())->toBe($name)
            ->and($parameters[$position]->allowsNull())->toBeFalse()
            ->and($parameters[$position]->isOptional())->toBeFalse();
    }
});

test('REQ-11-09 a wrong file type or a file over 5 MB is reported as soon as it is chosen, on both screens', function () {
    $request = officeVisitRequest();
    $show = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request]);
    $create = Livewire::actingAs($this->owner)->test(Create::class)->set('form.requester_mode', 'representative');

    foreach ([$show, $create] as $page) {
        $page->set('spa', UploadedFile::fake()->create('spa.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->assertHasErrors(['spa' => 'mimes'])->assertSee('The SPA must be a JPG, PNG, WebP or PDF file.')
            ->set('representativeId', UploadedFile::fake()->create('id.pdf', 5121, 'application/pdf'))
            ->assertHasErrors(['representativeId'])->assertSee('must be 5 MB or smaller.')
            ->set('representativeId', UploadedFile::fake()->create('id.pdf', 5120, 'application/pdf'))
            ->assertHasNoErrors('representativeId');
    }

    // Too big even for Livewire's temporary upload: the same plain words, not "12288 kilobytes".
    $show->set('spa', UploadedFile::fake()->create('spa.pdf', 13000, 'application/pdf'))
        ->assertHasErrors('spa')->assertSee('The file must be 5 MB or smaller.')->assertDontSee('kilobytes');

    $show->set('representativeName', 'Pedro Santos')->call('declareRepresentative')->assertHasErrors(['spa' => 'mimes']);
    expect($request->representatives()->count())->toBe(0);
});

test('REQ-11-10 the SPA and representative\'s ID use the valid ID\'s picker, its name a link to the viewer, on both screens', function () {
    $request = officeVisitRequest();
    $show = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])->html();
    $create = Livewire::actingAs($this->owner)->test(Create::class)->set('form.requester_mode', 'representative')->html();

    foreach ([$show, $create] as $html) {
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
    }
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
    $request = officeVisitRequest();

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->set('representativeName', 'Pedro Santos')
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
        ->set('representativeId', UploadedFile::fake()->image('id.jpg'))
        ->set('representativeContact', '0918 555 0101')
        ->call('declareRepresentative')
        ->assertHasErrors(['representativeContact' => 'regex'])
        ->set('representativeContact', '09185550101')
        ->call('declareRepresentative')
        ->assertHasNoErrors();

    expect($request->representatives()->sole()->contact_number)->toBe('09185550101');
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
