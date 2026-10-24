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

function releaseClaim(array $overrides = []): array
{
    return array_merge([
        'recipient_type' => RecipientType::Owner,
        'recipient_name' => test()->owner->name,
        'id_presented' => 'Passport P1234567',
        'identity_checked' => true,
        'acknowledged' => true,
    ], $overrides);
}

test('REQ-11-01 the owner declares a representative with SPA and ID when submitting', function () {
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->free->id)
        ->set('form.purpose', 'Employment')
        ->set('form.requester_mode', 'representative')
        ->call('submit')->assertHasErrors('form.representative_name')
        ->set('form.representative_name', 'Rosa Dela Cruz')
        ->set('form.representative_relationship', 'Mother')
        ->set('spa', UploadedFile::fake()->create('spa.pdf', 100, 'application/pdf'))
        ->set('representativeId', UploadedFile::fake()->image('id.jpg'))
        ->call('submit')
        ->assertHasNoErrors();

    $request = CredentialRequest::sole();
    $representative = $request->representatives()->sole();

    expect($request->requester_mode)->toBe(RequesterMode::Representative)
        ->and($representative)->full_name->toBe('Rosa Dela Cruz')->relationship->toBe('Mother')->status->toBe(ReviewStatus::Pending)
        ->and($request->documents()->pluck('kind')->all())->toBe([DocumentKind::Spa, DocumentKind::RepresentativeId]);

    $request->documents->each(fn ($document) => Storage::disk(RequestWorkflow::proofDisk())->assertExists($document->path));
});

test('REQ-11-01 the owner can add a representative later, documents optional', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])
        ->set('representativeName', 'Pedro Santos')
        ->call('declareRepresentative')
        ->assertHasNoErrors();

    expect($request->representatives()->sole()->full_name)->toBe('Pedro Santos')
        ->and($request->documents()->count())->toBe(0)
        ->and($request->events()->get()->last()->type)->toBe(RequestEventType::RepresentativeDeclared);
});

test('REQ-11-02 the representative is recorded apart from the owner and shown with their status', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);

    expect(User::where('first_name', 'Pedro')->exists())->toBeFalse();

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertSee('Pedro Santos')->assertSee('For verification');
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $request))->assertSee('Pedro Santos');
});

test('REQ-11-03 the registrar verifies or rejects a representative', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    $verified = workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Pedro Santos']);
    $rejected = workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Juan Cruz']);

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
    workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Pedro'], UploadedFile::fake()->image('spa.jpg'));
    $document = $request->documents()->sole();

    $this->actingAs($this->owner)->get(route('request-documents.show', $document))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->registrar)->get(route('request-documents.show', $document))->assertOk();
    $this->actingAs(requester($this->org->cba))->get(route('request-documents.show', $document))->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff($this->org->vpf)->create())->get(route('request-documents.show', $document))->assertNotFound();
});

test('REQ-11-05 the requester is told the credential is ready and what to bring', function () {
    $request = readyRequest();

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))
        ->assertSee('Your credential is ready for release')
        ->assertSee('Bring a valid ID');
});

test('REQ-11-06 the registrar records who received the credential', function () {
    $request = readyRequest();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->set('idPresented', 'Passport P1234567')
        ->set('identityChecked', true)
        ->set('acknowledged', true)
        ->call('release')
        ->assertHasNoErrors();

    $release = $request->release()->sole();
    expect($request->fresh())->status->toBe(RequestStatus::Released)->released_at->not->toBeNull()
        ->and($release)
        ->recipient_type->toBe(RecipientType::Owner)
        ->recipient_name->toBe($this->owner->name)
        ->id_presented->toBe('Passport P1234567')
        ->released_by->toBe($this->registrar->id)
        ->acknowledged->toBeTrue();
});

test('REQ-11-06 a verified representative can claim', function () {
    $request = readyRequest();
    $representative = workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Rosa Dela Cruz']);
    workflow()->reviewRepresentative($representative, $this->registrar, true);

    workflow()->release($request, $this->registrar, releaseClaim([
        'recipient_type' => RecipientType::Representative,
        'representative_id' => $representative->id,
        'recipient_name' => 'Rosa Dela Cruz',
    ]));

    expect($request->release()->sole()->request_representative_id)->toBe($representative->id);
});

test('REQ-11-07 no release to an unverified representative or without confirmations', function () {
    $request = readyRequest();
    $representative = workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Rosa Dela Cruz']);

    expect(fn () => workflow()->release($request, $this->registrar, releaseClaim([
        'recipient_type' => RecipientType::Representative, 'representative_id' => $representative->id, 'recipient_name' => 'Rosa',
    ])))->toThrow(InvalidTransition::class, 'must be verified');

    expect(fn () => workflow()->release($request, $this->registrar, releaseClaim(['acknowledged' => false])))->toThrow(InvalidTransition::class)
        ->and(fn () => workflow()->release($request, $this->registrar, releaseClaim(['identity_checked' => false])))->toThrow(InvalidTransition::class);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->set('idPresented', 'Passport')
        ->call('release')
        ->assertHasErrors(['identityChecked', 'acknowledged']);

    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);
});

test('REQ-11-08 the processing time in working days is stored at release', function () {
    Carbon::setTestNow('2026-10-09 10:00:00');   // Friday
    $request = readyRequest();

    Carbon::setTestNow('2026-10-14 15:00:00');   // Wednesday
    workflow()->release($request, $this->registrar, releaseClaim());

    expect($request->fresh()->processing_working_days)->toBe(4);   // Sat, Mon, Tue, Wed
});
