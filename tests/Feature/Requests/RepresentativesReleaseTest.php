<?php

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

test('REQ-11-01 a representative added later needs the SPA and their valid ID', function () {
    $request = submitRequest($this->owner, $this->org->tor);

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
    $request = submitRequest($this->owner, $this->org->tor);
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

test('REQ-11-01 a representative\'s contact number, when given, must be exactly 11 digits', function () {
    $request = submitRequest($this->owner, $this->org->tor);

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
    declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);

    expect(User::where('first_name', 'Pedro')->exists())->toBeFalse();

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertSee('Pedro Santos')->assertSee('For verification');
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $request))->assertSee('Pedro Santos');
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
        ->assertSee('Bring a valid ID');
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
