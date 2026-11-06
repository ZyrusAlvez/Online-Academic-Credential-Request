<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Index as RegistrarRequestIndex;
use App\Livewire\Registrar\Requests\Show as RegistrarRequestShow;
use App\Livewire\Student\Requests\Create;
use App\Livewire\Student\Requests\Show as StudentRequestShow;
use App\Models\CredentialRequest;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
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

test('REQ-15-01 there is no Identity Checks page or nav item', function () {
    expect(Route::has('registrar.identities'))->toBeFalse();

    $this->actingAs($this->registrar)->get('/registrar/identities')->assertNotFound();
    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()->assertDontSee('Identity Checks');
});

test('REQ-15-02 there is no My Profile page; the old address goes to Settings → Profile', function () {
    expect(Route::has('student.profile'))->toBeFalse();

    $this->actingAs($this->newcomer)->get('/student/profile')->assertRedirect('/settings/profile');
    $this->actingAs($this->newcomer)->get(route('student.requests.create'))
        ->assertOk()
        ->assertDontSee('My Profile')
        ->assertSeeInOrder(['New Request', 'My Requests']);
});

test('REQ-15-03 New Request has numbered sections for the request, personal and academic information, the ID and who transacts', function () {
    Livewire::actingAs($this->newcomer)->test(Create::class)
        ->assertSeeInOrder([
            '1. What do you need?', '2. Personal information', '3. Academic information', '4. Valid ID',
            '5. Who will transact?', 'Important notice: incomplete requirements', 'Review request',
        ])
        ->assertDontSee('Verify your profile before making a request');
});

test('REQ-15-03 the personal and academic sections start filled in from the saved profile', function () {
    $owner = User::factory()->withProfile($this->org->ccs)->create([
        'first_name' => 'Maria', 'last_name' => 'Reyes', 'contact_number' => '09181112222',
        'program' => 'BS Computer Science', 'student_number' => '19-0001-123',
    ]);

    Livewire::actingAs($owner)->test(Create::class)
        ->assertSet('profile.first_name', 'Maria')
        ->assertSet('profile.last_name', 'Reyes')
        ->assertSet('profile.contact_number', '09181112222')
        ->assertSet('profile.college_id', $this->org->ccs->id)
        ->assertSet('profile.program', 'BS Computer Science')
        ->assertSet('profile.student_number', '19-0001-123');
});

test('REQ-15-04 a saved ID is shown and used; without one an ID is required', function () {
    reviewRequest($this->newcomer, ['profile.id_type' => '', 'profile.id_file' => null])
        ->assertHasErrors(['profile.id_type' => 'required', 'profile.id_file' => 'required'])
        ->assertSet('reviewing', false);

    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);
    $saved = $owner->latestIdentityDocument()->firstOrFail();

    Livewire::actingAs($owner)->test(Create::class)
        ->assertSee('Your saved ID')
        ->assertSee("{$saved->id_type}: {$saved->original_name}")
        ->assertSee('It is used unless you choose a different one below.')
        ->assertSeeHtml('data-id-preview');

    reviewRequest($owner)->assertHasNoErrors()->call('submit');

    expect(CredentialRequest::sole()->identity_document_id)->toBe($saved->id);
});

test('REQ-15-04 a different ID can be chosen for the request, and it becomes the saved ID', function () {
    $owner = User::factory()->withProfile($this->org->ccs)->create(['program' => 'BS Computer Science']);

    reviewRequest($owner, ['profile.id_type' => 'UMID', 'profile.id_file' => UploadedFile::fake()->image('umid.jpg')])
        ->assertSee('UMID: umid.jpg')
        ->call('submit')
        ->assertHasNoErrors();

    $new = $owner->latestIdentityDocument()->firstOrFail();
    expect($new->id_type)->toBe('UMID')
        ->and(CredentialRequest::sole()->identity_document_id)->toBe($new->id)
        ->and($owner->identityDocuments()->count())->toBe(2);
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

test('REQ-15-05 the summary shows the request, the details, the ID and the privacy notice', function () {
    $this->newcomer->forceFill(['privacy_consented_at' => null])->save();

    reviewRequest($this->newcomer)
        ->assertSee('Summary')
        ->assertSeeInOrder([
            'Transcript of Records', 'Employment', 'Personally',
            'Juan', 'Santos', 'Dela Cruz', '09171234567',
            'Graduate', 'College of Computer Studies', 'BS Computer Science', '2019 to 2023',
            'Passport: passport.jpg',
            'Data Privacy Act of 2012 (RA 10173)', 'I agree to the processing of my personal data',
            'Back to edit', 'Submit request',
        ])
        ->assertSeeHtml('data-id-summary-preview');
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

test('REQ-15-05 every request asks for the consent again with an unticked checkbox, and Submit waits for it', function () {
    $owner = requester($this->org->ccs);   // consented on an earlier save
    $consentedAt = $owner->privacy_consented_at;

    $page = reviewRequest($owner, ['profile.privacy_consent' => false])
        ->assertSet('reviewing', true)
        ->assertSee('I agree to the processing of my personal data')
        ->assertSee('Tick the box to continue.')
        ->assertDontSee('You agreed to the processing of your personal data');

    expect($page->html())->toMatch('/<button[^>]*x-bind:disabled="! \$wire\.profile\.privacy_consent"[^>]*disabled[^>]*data-submit-request/');

    // A forged click is refused by the server too.
    $page->call('submit')->assertHasErrors(['profile.privacy_consent' => 'accepted']);
    expect(CredentialRequest::count())->toBe(0);

    $page->set('profile.privacy_consent', true)->call('submit')->assertHasNoErrors();
    expect(CredentialRequest::count())->toBe(1)
        ->and($owner->fresh()->privacy_consented_at->equalTo($consentedAt))->toBeTrue();
});

test('REQ-15-05 going back keeps every entry, and submitting from the form only reviews', function () {
    $component = reviewRequest($this->newcomer)
        ->call('back')
        ->assertSet('reviewing', false)
        ->assertSet('profile.first_name', 'Juan')
        ->assertSet('form.purpose', 'Employment')
        ->call('submit')
        ->assertSet('reviewing', true);

    expect(CredentialRequest::count())->toBe(0);

    $component->call('submit')->assertHasNoErrors();
    expect(CredentialRequest::count())->toBe(1);
});

test('REQ-15-06 submitting saves the profile and the ID and copies them onto the request', function () {
    reviewRequest($this->newcomer)->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    $owner = $this->newcomer->fresh();
    $id = $owner->latestIdentityDocument()->firstOrFail();

    expect($owner)
        ->first_name->toBe('Juan')
        ->contact_number->toBe('09171234567')
        ->college_id->toBe($this->org->ccs->id)
        ->program->toBe('BS Computer Science')
        ->privacy_consented_at->not->toBeNull();
    Storage::disk(RequestWorkflow::proofDisk())->assertExists($id->path);

    expect(CredentialRequest::sole())
        ->requester_name->toBe('Juan Santos Dela Cruz')
        ->contact_number->toBe('09171234567')
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
        ->assertSee('Your saved ID')
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

test('REQ-15-08 request pages, lists and the CSV show the request\'s copy of the name', function () {
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);
    $request->forceFill(['requester_name' => 'Copied Name On Request'])->save();
    $owner->forceFill(['first_name' => 'Renamed'])->save();

    $this->actingAs($this->registrar)->get(route('registrar.requests'))->assertSee('Copied Name On Request')->assertDontSee('Renamed');
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $request))->assertSee('Copied Name On Request');
    expect($this->actingAs($this->registrar)->get(route('registrar.requests.export'))->streamedContent())->toContain('Copied Name On Request');
    $this->actingAs($owner)->get(route('student.requests.show', $request))->assertSee('Copied Name On Request');
});

test('REQ-15-09 the registrar sees the request\'s details and ID and can verify it, which is logged and told to the requester', function () {
    Notification::fake();
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    Livewire::actingAs($this->registrar)->test(RegistrarRequestShow::class, ['credentialRequest' => $request])
        ->assertSee($request->requester_name)
        ->assertSee($request->contact_number)
        ->assertSee($owner->email)
        ->assertSee('ID: For verification')
        ->assertSee('Verify identity')
        ->call('verifyIdentity')
        ->assertDispatched('notify', message: 'Identity verified.')
        ->assertSee('ID: Verified')
        ->assertDontSee('Verify identity');

    expect($request->fresh())
        ->identity_status->toBe(ReviewStatus::Verified)
        ->identity_reviewed_by->toBe($this->registrar->id)
        ->identity_reviewed_at->not->toBeNull()
        ->and($request->events()->where('type', RequestEventType::IdentityVerified)->count())->toBe(1);
    Notification::assertSentTo($owner, RequestActivity::class, fn (RequestActivity $n, array $channels) => $n->event->type === RequestEventType::IdentityVerified && in_array('mail', $channels, true));
});

test('REQ-15-09 rejecting needs a reason, which the requester is told', function () {
    Notification::fake();
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);

    Livewire::actingAs($this->registrar)->test(RegistrarRequestShow::class, ['credentialRequest' => $request])
        ->call('rejectIdentity')
        ->assertHasErrors('identityRemarks')
        ->set('identityRemarks', 'The photo is blurred.')
        ->call('rejectIdentity')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: 'ID rejected. The requester was asked to upload another.')
        ->assertSee('Rejected: The photo is blurred.');

    expect($request->fresh())
        ->identity_status->toBe(ReviewStatus::Rejected)
        ->identity_remarks->toBe('The photo is blurred.');
    Notification::assertSentTo($owner, RequestActivity::class, fn (RequestActivity $n) => $n->event->type === RequestEventType::IdentityRejected
        && $n->event->remarks === 'The photo is blurred.');
});

test('REQ-15-09 an ID can be reviewed only once and only while the requirements are evaluated', function () {
    $request = submitRequest(requester($this->org->ccs), $this->org->free);
    $page = Livewire::actingAs($this->registrar)->test(RegistrarRequestShow::class, ['credentialRequest' => $request]);

    workflow()->reviewIdentity($request, User::factory()->registrar()->create(), true);

    $page->call('verifyIdentity')->assertDispatched('notify', message: 'This ID has already been reviewed.');
    $page->set('identityRemarks', 'Wrong person')->call('rejectIdentity')
        ->assertDispatched('notify', message: 'This ID has already been reviewed.')
        ->assertSet('identityRemarks', 'Wrong person');

    $accepted = acceptRequest(submitRequest(requester($this->org->ccs), $this->org->free), $this->registrar);
    $accepted->forceFill(['identity_status' => ReviewStatus::Pending])->save();
    expect(fn () => workflow()->reviewIdentity($accepted, $this->registrar, true))->toThrow(InvalidTransition::class);
});

test('REQ-15-09 only registrars review IDs', function () {
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);

    Livewire::actingAs($owner)->test(RegistrarRequestShow::class, ['credentialRequest' => $request])->assertForbidden();
    expect($request->fresh()->identity_status)->toBe(ReviewStatus::Pending);
});

test('REQ-15-10 the requirements cannot be marked complete until the request\'s ID is verified', function () {
    $request = submitRequest(requester($this->org->ccs), $this->org->free);
    workflow()->startEvaluation($request, $this->registrar);

    expect(fn () => workflow()->completeEvaluation($request, $this->registrar))
        ->toThrow(InvalidTransition::class, "Verify the requester's valid ID before marking the requirements complete.");

    workflow()->reviewIdentity($request, $this->registrar, false, 'Blurred.');
    expect(fn () => workflow()->completeEvaluation($request, $this->registrar))->toThrow(InvalidTransition::class);

    // A verified ID on another request of the same requester does not count.
    $other = submitRequest($request->student, $this->org->free);
    workflow()->reviewIdentity($other, $this->registrar, true);
    expect(fn () => workflow()->completeEvaluation($request->fresh(), $this->registrar))->toThrow(InvalidTransition::class);
});

test('REQ-15-11 after a rejection the requester uploads another ID on the request page', function () {
    Notification::fake();
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);
    workflow()->reviewIdentity($request, $this->registrar, false, 'The photo is blurred.');

    Livewire::actingAs($owner)->test(StudentRequestShow::class, ['credentialRequest' => $request])
        ->assertSee('Your valid ID was not accepted')
        ->assertSee('The photo is blurred.')
        ->call('replaceIdentity')
        ->assertHasErrors(['newIdType' => 'required', 'newIdFile' => 'required'])
        ->set('newIdType', 'UMID')
        ->set('newIdFile', UploadedFile::fake()->image('umid.jpg'))
        ->call('replaceIdentity')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertDontSee('Your valid ID was not accepted');

    $new = $owner->latestIdentityDocument()->firstOrFail();
    Storage::disk(RequestWorkflow::proofDisk())->assertExists($new->path);
    expect($new->id_type)->toBe('UMID')
        ->and($request->fresh())
        ->identity_document_id->toBe($new->id)
        ->identity_status->toBe(ReviewStatus::Pending)
        ->identity_remarks->toBeNull()
        ->and($request->events()->where('type', RequestEventType::IdentityResubmitted)->count())->toBe(1);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn (RequestActivity $n) => $n->title === "Valid ID to verify for {$request->reference_no}");
});

test('REQ-15-11 no upload is offered or accepted unless the request\'s ID was rejected and the request is still evaluated', function () {
    $owner = requester($this->org->ccs);
    $request = submitRequest($owner, $this->org->tor);

    Livewire::actingAs($owner)->test(StudentRequestShow::class, ['credentialRequest' => $request])
        ->assertDontSeeHtml('data-replace-id')
        ->set('newIdType', 'UMID')
        ->set('newIdFile', UploadedFile::fake()->image('umid.jpg'))
        ->call('replaceIdentity')
        ->assertForbidden();

    workflow()->reviewIdentity($request, $this->registrar, false, 'Blurred.');
    workflow()->decline($request, $this->registrar, 'Not eligible.');

    Livewire::actingAs($owner)->test(StudentRequestShow::class, ['credentialRequest' => $request])
        ->assertDontSeeHtml('data-replace-id');
    expect($owner->identityDocuments()->count())->toBe(1);
});

test('REQ-15-11 another requester cannot replace the ID of a request', function () {
    $request = submitRequest(requester($this->org->ccs), $this->org->tor);
    workflow()->reviewIdentity($request, $this->registrar, false, 'Blurred.');
    $intruder = requester($this->org->ccs);

    expect(fn () => workflow()->replaceIdentity($request, $intruder, $intruder->latestIdentityDocument()->firstOrFail()))
        ->toThrow(InvalidTransition::class);
    $this->actingAs($intruder)->get(route('student.requests.show', $request))->assertNotFound();
});

test('REQ-15-12 the registrar\'s list marks the requests whose ID awaits verification', function () {
    $pending = submitRequest(requester($this->org->ccs), $this->org->tor);
    $verified = submitRequest(requester($this->org->ccs), $this->org->tor);
    workflow()->reviewIdentity($verified, $this->registrar, true);

    $html = Livewire::actingAs($this->registrar)->test(RegistrarRequestIndex::class)->html();

    expect(substr_count($html, 'ID to verify'))->toBe(1);

    workflow()->reviewIdentity($pending, $this->registrar, true);
    expect(Livewire::actingAs($this->registrar)->test(RegistrarRequestIndex::class)->html())->not->toContain('ID to verify');
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

test('REQ-15-14 the migration copies each owner\'s name, contact and latest ID onto their requests and drops the account status', function () {
    expect(Schema::hasColumns('users', ['identity_status', 'identity_verified_at']))->toBeFalse()
        ->and(Schema::hasColumns('identity_documents', ['status', 'reviewed_by', 'reviewed_at', 'remarks']))->toBeFalse()
        ->and(Schema::hasColumns('credential_requests', ['requester_name', 'contact_number', 'identity_document_id', 'identity_status', 'identity_reviewed_by', 'identity_reviewed_at', 'identity_remarks']))->toBeTrue();

    $migration = require database_path('migrations/2026_11_06_000003_move_identity_check_to_requests.php');
    $owner = User::factory()->withProfile($this->org->ccs)->create(['first_name' => 'Ana', 'middle_name' => 'Luz', 'last_name' => 'Mendoza', 'contact_number' => '09170000001']);
    $request = CredentialRequest::factory()->create(['user_id' => $owner->id, 'requester_name' => '', 'contact_number' => null]);

    $migration->down();
    $latest = $owner->latestIdentityDocument()->firstOrFail();
    DB::table('identity_documents')->where('id', $latest->id)->update(['status' => 'verified', 'reviewed_by' => $this->registrar->id, 'remarks' => null]);
    $migration->up();

    expect($request->fresh())
        ->requester_name->toBe('Ana Luz Mendoza')
        ->contact_number->toBe('09170000001')
        ->identity_document_id->toBe($latest->id)
        ->identity_status->toBe(ReviewStatus::Verified)
        ->identity_reviewed_by->toBe($this->registrar->id);
});
