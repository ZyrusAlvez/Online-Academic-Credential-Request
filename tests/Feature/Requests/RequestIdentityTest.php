<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Student\Requests\Create;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Spec 15: the requester's details and ID are given on New Request, saved for next time, copied
| onto the request and checked there by the registrar.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->newcomer = User::factory()->student()->create(['first_name' => null, 'last_name' => null]);
});

/**
 * Fill in New Request for the TOR, then review it.
 *
 * @param  array<string, mixed>  $overrides
 */
function reviewRequest(User $user, array $overrides = []): Testable
{
    $component = Livewire::actingAs($user)->test(Create::class)
        ->set('form.credential_type_id', test()->org->tor->id)
        ->set('form.purpose', 'Employment');

    $needsId = ! $user->latestIdentityDocument()->exists();

    foreach (array_merge([
        'profile.first_name' => 'Juan',
        'profile.middle_name' => 'Santos',
        'profile.last_name' => 'Dela Cruz',
        'profile.sex' => 'male',
        'profile.contact_number' => '09171234567',
        'profile.academic_status' => 'graduate',
        'profile.college_id' => test()->org->ccs->id,
        'profile.program' => 'BS Computer Science',
        'profile.attended_from' => 2019,
        'profile.attended_to' => 2023,
        'profile.privacy_consent' => true,
    ], $needsId ? ['profile.id_type' => 'Passport', 'profile.id_file' => UploadedFile::fake()->image('passport.jpg')] : [], $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('review');
}

test('REQ-15-04 a saved ID is shown and used; without one an ID is required', function () {
    reviewRequest($this->newcomer, ['profile.id_type' => '', 'profile.id_file' => null])
        ->assertHasErrors(['profile.id_type' => 'required', 'profile.id_file' => 'required'])
        ->assertSet('reviewing', false);

    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);
    $saved = $owner->latestIdentityDocument()->firstOrFail();

    Livewire::actingAs($owner)->test(Create::class)
        ->assertSee('Your Saved ID')
        ->assertSee("{$saved->id_type}: {$saved->original_name}")
        ->assertDontSee('To use a different ID, remove this one first.')
        ->assertSeeHtml('data-remove-saved-id')
        ->assertDontSeeHtml('data-id-preview')
        ->assertDontSeeHtml('wire:model.live="profile.id_type"');

    reviewRequest($owner)->assertHasNoErrors()->call('submit');

    expect(CredentialRequest::sole()->identity_document_id)->toBe($saved->id);
});

test('REQ-15-04 with a saved ID no second ID is accepted, even sent from the browser', function () {
    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);

    reviewRequest($owner, ['profile.id_type' => 'UMID', 'profile.id_file' => UploadedFile::fake()->image('umid.jpg')])
        ->assertHasErrors(['profile.id_type' => 'prohibited', 'profile.id_file' => 'prohibited'])
        ->assertSee('Remove your saved ID first to use a different one.')
        ->assertSet('reviewing', false);

    expect($owner->identityDocuments()->count())->toBe(1);
});

test('REQ-15-18 New Request removes the saved ID once confirmed; then an ID is required and the new one becomes the saved ID', function () {
    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);
    $old = $owner->latestIdentityDocument()->firstOrFail();

    $html = Livewire::actingAs($owner)->test(Create::class)->html();
    expect($html)->toMatch('/<button[^>]*wire:click="removeSavedId"[^>]*data-confirm-variant="danger"[^>]*wire:confirm="Remove your saved ID\?/');

    $component = Livewire::actingAs($owner)->test(Create::class)
        ->call('removeSavedId')
        ->assertDispatched('notify')
        ->assertDontSee('Your Saved ID')
        ->assertSeeHtml('wire:model.live="profile.id_type"')
        ->assertSeeHtml('data-id-preview');

    expect(IdentityDocument::find($old->id))->toBeNull();
    Storage::disk(RequestWorkflow::proofDisk())->assertMissing($old->path);

    reviewRequest($owner, ['profile.id_type' => '', 'profile.id_file' => null])
        ->assertHasErrors(['profile.id_type' => 'required', 'profile.id_file' => 'required']);

    reviewRequest($owner, ['profile.id_type' => 'UMID', 'profile.id_file' => UploadedFile::fake()->image('umid.jpg')])
        ->assertSeeInOrder(['4. Valid ID', 'UMID', 'umid.jpg'])
        ->call('submit')
        ->assertHasNoErrors();

    $new = $owner->latestIdentityDocument()->firstOrFail();
    expect($new->id_type)->toBe('UMID')
        ->and(CredentialRequest::sole()->identity_document_id)->toBe($new->id)
        ->and($owner->identityDocuments()->count())->toBe(1);
});

test('REQ-15-18 a removed ID that a request was sent with stays on that request, and the registrar can still open it', function () {
    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);
    reviewRequest($owner)->call('submit')->assertHasNoErrors();
    $sent = CredentialRequest::sole()->identityDocument;
    Storage::disk(RequestWorkflow::proofDisk())->put($sent->path, 'id');

    Livewire::actingAs($owner)->test(Create::class)->call('removeSavedId');

    expect($owner->latestIdentityDocument()->first())->toBeNull()
        ->and($sent->fresh()->removed_at)->not->toBeNull()
        ->and(CredentialRequest::sole()->identity_document_id)->toBe($sent->id)
        ->and(ActivityLog::query()->where('action', 'identity_document.updated')->sole()->description)
        ->toBe("Removed their saved valid ID ({$sent->id_type})");
    Storage::disk(RequestWorkflow::proofDisk())->assertExists($sent->path);

    $this->actingAs($this->registrar)->get(route('identity-documents.show', $sent))->assertOk();
});

test('REQ-15-05 reviewing checks every entry, lists the errors in the modal and saves nothing', function () {
    reviewRequest($this->newcomer, ['form.purpose' => '', 'profile.first_name' => '', 'profile.contact_number' => '12'])
        ->assertHasErrors(['form.purpose', 'profile.first_name', 'profile.contact_number'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);

    reviewRequest($this->newcomer)->assertHasNoErrors()->assertSet('reviewing', true);

    expect(CredentialRequest::count())->toBe(0)
        ->and(IdentityDocument::count())->toBe(0)
        ->and($this->newcomer->fresh()->first_name)->toBeNull();
});

test('REQ-15-05 the consent is required on the summary and recorded once given', function () {
    $this->newcomer->forceFill(['privacy_consented_at' => null])->save();

    reviewRequest($this->newcomer, ['profile.privacy_consent' => false])
        ->assertSet('reviewing', true)
        ->call('submit')
        ->assertHasErrors(['profile.privacy_consent' => 'accepted'])
        ->assertSet('reviewing', true)
        ->set('profile.privacy_consent', true)
        ->call('submit')
        ->assertHasNoErrors();

    expect($this->newcomer->fresh()->privacy_consented_at)->not->toBeNull()
        ->and(CredentialRequest::count())->toBe(1);
});

test('REQ-15-06 submitting saves the profile and the ID and copies them onto the request', function () {
    reviewRequest($this->newcomer)->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    $owner = $this->newcomer->fresh();
    $id = $owner->latestIdentityDocument()->firstOrFail();

    expect($owner)
        ->first_name->toBe('Juan')
        ->contact_number->toBe('+639171234567')
        ->college_id->toBe($this->org->ccs->id)
        ->program->toBe('BS Computer Science')
        ->privacy_consented_at->not->toBeNull();
    Storage::disk(RequestWorkflow::proofDisk())->assertExists($id->path);

    expect(CredentialRequest::sole())
        ->requester_name->toBe('Juan Santos Dela Cruz')
        ->contact_number->toBe('+639171234567')
        ->college_id->toBe($this->org->ccs->id)
        ->program->toBe('BS Computer Science')
        ->attended_from->toBe(2019)
        ->identity_document_id->toBe($id->id)
        ->identity_status->toBe(ReviewStatus::Pending)
        ->status->toBe(RequestStatus::Submitted);
});

test('REQ-15-06 the next request starts filled in from what was submitted', function () {
    reviewRequest($this->newcomer)->call('submit')->assertHasNoErrors();

    Livewire::actingAs($this->newcomer->fresh())->test(Create::class)
        ->assertSet('profile.first_name', 'Juan')
        ->assertSet('profile.program', 'BS Computer Science')
        ->assertSee('Your Saved ID')
        ->set('form.credential_type_id', $this->org->free->id)
        ->set('form.purpose', 'Employment')
        ->call('review')
        ->assertHasNoErrors()
        ->set('profile.privacy_consent', true)   // ticked again on every summary (REQ-15-05)
        ->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::count())->toBe(2)
        ->and(IdentityDocument::count())->toBe(1);
});

test('REQ-15-06 if the request cannot be submitted nothing is saved and no file is kept', function () {
    $component = reviewRequest($this->newcomer)->assertSet('reviewing', true);
    $this->org->tor->update(['is_active' => false]);

    $component->call('submit')
        ->assertHasErrors('form.credential_type_id')
        ->assertSet('reviewing', false)
        ->assertDispatched('modal-show', name: 'profile-errors');

    expect(CredentialRequest::count())->toBe(0)
        ->and(IdentityDocument::count())->toBe(0)
        ->and($this->newcomer->fresh()->first_name)->toBeNull()
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toBe([]);
});

test('REQ-15-06 when the ID cannot be stored nothing is saved and the error shows on the form', function () {
    $this->mock(SubmitIdentityDocument::class, fn ($mock) => $mock->shouldReceive('handle')
        ->andThrow(ValidationException::withMessages(['idFile' => 'The file could not be saved. Please try again.'])));

    reviewRequest($this->newcomer)->call('submit')
        ->assertHasErrors('profile.id_file')
        ->assertSet('reviewing', false);

    expect(CredentialRequest::count())->toBe(0)
        ->and($this->newcomer->fresh()->first_name)->toBeNull();
});

test('REQ-15-06 the workflow refuses a request without the details or with someone else\'s ID', function () {
    expect(fn () => submitRequest($this->newcomer, $this->org->tor))->toThrow(InvalidTransition::class);

    $owner = requester($this->org->ccs);
    $foreign = IdentityDocument::factory()->create();

    expect(fn () => workflow()->submit($owner, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'], $foreign))
        ->toThrow(InvalidTransition::class, 'Upload a valid ID before requesting a credential.');

    $owner->identityDocuments()->delete();
    expect(fn () => submitRequest($owner->fresh(), $this->org->tor))->toThrow(InvalidTransition::class);
    expect(CredentialRequest::count())->toBe(0);
});

test('REQ-15-08 a request keeps its copy after the profile and saved ID change', function () {
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);
    $original = $request->identity_document_id;

    $owner->forceFill(['first_name' => 'Changed', 'contact_number' => '09990000000', 'program' => 'Changed Program'])->save();
    app(SubmitIdentityDocument::class)->handle($owner, 'UMID', UploadedFile::fake()->image('umid.jpg'));

    expect($request->fresh())
        ->requester_name->not->toContain('Changed')
        ->contact_number->not->toBe('09990000000')
        ->program->toBe('BS Computer Science')
        ->identity_document_id->toBe($original);
});

test('REQ-15-13 only the owner and registrars can open the request\'s ID; offices cannot', function () {
    $owner = requester($this->org->ccs);
    $request = acceptRequest(submitRequest($owner, $this->org->free), $this->registrar);
    $office = User::factory()->departmentStaff($this->org->registrarOffice)->create();
    Storage::disk(RequestWorkflow::proofDisk())->put($request->identityDocument->path, 'id');

    $this->actingAs($owner)->get(route('identity-documents.show', $request->identity_document_id))->assertOk();
    $this->actingAs($this->registrar)->get(route('identity-documents.show', $request->identity_document_id))->assertOk();
    $this->actingAs($office)->get(route('identity-documents.show', $request->identity_document_id))->assertNotFound();
});
