<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\IdentityStatus;
use App\Enums\ReviewStatus;
use App\Livewire\Registrar\Identities\Index;
use App\Livewire\Student\Profile;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\Program;
use App\Models\User;
use App\Notifications\IdentityReviewed;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->college = Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    Program::factory()->for($this->college, 'college')->create(['name' => 'BS Computer Science']);
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
});

function reviewProfile(array $overrides = [], ?User $user = null): Testable
{
    $user ??= test()->student;
    $component = Livewire::actingAs($user)->test(Profile::class);

    // A requester with no ID pending or verified must give one (REQ-01-22).
    $id = $user->fresh()->needsValidId()
        ? ['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg')]
        : [];

    foreach (array_merge([
        'form.first_name' => 'Juan',
        'form.middle_name' => '',
        'form.last_name' => 'Dela Cruz',
        'form.contact_number' => '09171234567',
        'form.academic_status' => 'graduate',
        'form.college_id' => test()->college->id,
        'form.program' => 'BS Computer Science',
        'form.student_number' => '',
        'form.attended_from' => 2019,
        'form.attended_to' => 2023,
    ], $id, $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('review');
}

function fillProfile(array $overrides = [], ?User $user = null): Testable
{
    $component = reviewProfile(['form.privacy_consent' => true, ...$overrides], $user);

    // Invalid entries stop at the form, as they do in the browser.
    return $component->errors()->isEmpty() ? $component->call('save') : $component;
}

function uploadId(?User $user = null): IdentityDocument
{
    fillProfile(['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg')], $user)
        ->assertHasNoErrors();

    return IdentityDocument::latest('id')->firstOrFail();
}

test('REQ-01-16 a requester can save their academic information; the student number is optional', function () {
    fillProfile()->assertHasNoErrors();

    expect($this->student->fresh())
        ->academic_status->toBe(AcademicStatus::Graduate)
        ->college_id->toBe($this->college->id)
        ->program->toBe('BS Computer Science')
        ->student_number->toBeNull()
        ->attended_from->toBe(2019)
        ->attended_to->toBe(2023)
        ->contact_number->toBe('09171234567');
});

test('REQ-01-16 the contact number must be exactly 11 digits', function () {
    foreach (['0917 123 4567', '+639171234567', '0917123456', '091712345678', '0917-123-4567'] as $number) {
        fillProfile(['form.contact_number' => $number])->assertHasErrors(['form.contact_number' => 'regex']);
    }

    fillProfile(['form.contact_number' => '09171234567'])->assertHasNoErrors();
});

test('REQ-01-16 the years attended and college are validated', function () {
    $office = Department::factory()->create();

    fillProfile(['form.attended_from' => 2024, 'form.attended_to' => 2020])->assertHasErrors(['form.attended_to' => 'gte']);
    fillProfile(['form.attended_from' => 1900])->assertHasErrors(['form.attended_from' => 'between']);
    fillProfile(['form.attended_to' => now()->year + 1])->assertHasErrors(['form.attended_to' => 'between']);
    fillProfile(['form.college_id' => $office->id])->assertHasErrors(['form.college_id' => 'exists']);
});

test('REQ-01-25 one save stores the name, contact, academic information, consent and ID together', function () {
    $this->student->forceFill(['first_name' => 'Old', 'last_name' => 'Name', 'privacy_consented_at' => null])->save();

    fillProfile([
        'form.middle_name' => 'Santos',
        'form.id_type' => 'Passport',
        'form.id_file' => UploadedFile::fake()->image('passport.jpg'),
    ])->assertHasNoErrors()->assertDispatched('notify');

    expect($this->student->fresh())
        ->first_name->toBe('Juan')
        ->middle_name->toBe('Santos')
        ->last_name->toBe('Dela Cruz')
        ->privacy_consented_at->not->toBeNull()
        ->identity_status->toBe(IdentityStatus::Pending)
        ->and($this->student->identityDocuments()->count())->toBe(1);
});

test('REQ-01-25 the first save needs the data privacy consent; later saves do not ask again', function () {
    $this->student->forceFill(['privacy_consented_at' => null])->save();

    fillProfile(['form.privacy_consent' => false])->assertHasErrors(['form.privacy_consent' => 'accepted']);
    expect($this->student->fresh()->privacy_consented_at)->toBeNull();

    fillProfile()->assertHasNoErrors();
    $consentedAt = $this->student->fresh()->privacy_consented_at;

    fillProfile(['form.privacy_consent' => false])->assertHasNoErrors();
    expect($this->student->fresh()->privacy_consented_at->equalTo($consentedAt))->toBeTrue();
});

test('REQ-01-22 the profile form has a single button, which reviews instead of saving', function () {
    $html = $this->actingAs($this->student)->get(route('student.profile'))->assertOk()->getContent();

    expect(substr_count($html, 'wire:submit'))->toBe(1)
        ->and($html)->toContain('>Confirm</')
        ->and($html)->not->toContain('Review and confirm')
        ->and($html)->not->toContain('Upload ID')
        ->and($html)->not->toContain('Confirm and save')
        ->and($html)->not->toContain('Data Privacy Act');
});

test('REQ-01-22 reviewing saves nothing, and invalid entries stay on the form', function () {
    $this->student->forceFill(['first_name' => 'Old', 'privacy_consented_at' => null])->save();

    reviewProfile(['form.first_name' => ''])
        ->assertHasErrors(['form.first_name' => 'required'])
        ->assertSet('reviewing', false)
        ->assertDontSee('Confirm and save');

    reviewProfile(['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg')])
        ->assertHasNoErrors()
        ->assertSet('reviewing', true);

    expect($this->student->fresh())
        ->first_name->toBe('Old')
        ->privacy_consented_at->toBeNull()
        ->and(IdentityDocument::count())->toBe(0);
});

test('REQ-01-26 choosing Confirm with invalid entries opens the modal listing them', function () {
    reviewProfile(['form.first_name' => '', 'form.contact_number' => '123'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertHasErrors(['form.first_name' => 'required', 'form.contact_number' => 'regex'])
        ->assertSeeInOrder(['Some details need your attention', 'The first name field is required.', 'Enter an 11-digit mobile number'])
        ->assertSet('reviewing', false);
});

test('REQ-01-26 valid entries go to the summary without the modal', function () {
    reviewProfile()->assertNotDispatched('modal-show')->assertSet('reviewing', true);
});

test('REQ-01-26 the browser\'s own validation does not replace the modal', function () {
    $this->actingAs($this->student)->get(route('student.profile'))
        ->assertOk()
        ->assertSeeHtml('wire:submit="review" novalidate');
});

test('REQ-01-26 a type of ID needs a file, and a file needs a type', function () {
    $this->student->forceFill(['identity_status' => IdentityStatus::Pending])->save();

    reviewProfile(['form.id_type' => 'Passport'])
        ->assertHasErrors(['form.id_file' => 'required_with'])
        ->assertDispatched('modal-show', name: 'profile-errors');
    reviewProfile(['form.id_file' => UploadedFile::fake()->image('id.jpg')])
        ->assertHasErrors(['form.id_type' => 'required_with']);
});

test('REQ-01-26 an entry that fails on saving returns to the form with the modal', function () {
    reviewProfile(['form.privacy_consent' => true])
        ->set('form.contact_number', '123')
        ->call('save')
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);
});

test('REQ-01-27 a verified requester\'s name is locked, with a note, and a forged change is ignored', function () {
    $verified = User::factory()->withProfile($this->college)->create(['first_name' => 'Maria', 'middle_name' => 'Luz', 'last_name' => 'Reyes']);

    $this->actingAs($verified)->get(route('student.profile'))
        ->assertOk()
        ->assertSee('Your name is locked because your ID is verified.');

    reviewProfile([
        'form.first_name' => 'Forged',
        'form.middle_name' => '',
        'form.last_name' => 'Name',
        'form.privacy_consent' => true,
    ], $verified)
        ->assertHasNoErrors()
        ->assertSet('form.first_name', 'Maria')
        ->assertSee('Maria')
        ->assertDontSee('Forged')
        ->call('save')
        ->assertHasNoErrors();

    expect($verified->fresh())
        ->first_name->toBe('Maria')
        ->middle_name->toBe('Luz')
        ->last_name->toBe('Reyes');
});

test('REQ-01-27 a name stays editable until the identity is verified', function () {
    foreach ([IdentityStatus::Unsubmitted, IdentityStatus::Pending, IdentityStatus::Rejected] as $status) {
        $this->student->forceFill(['identity_status' => $status, 'first_name' => 'Old'])->save();

        expect($this->student->fresh()->hasLockedName())->toBeFalse();
        fillProfile()->assertHasNoErrors();
        expect($this->student->fresh()->first_name)->toBe('Juan');
    }
});

test('REQ-01-28 a registrar corrects a verified requester\'s name, and it is logged', function () {
    Log::spy();
    $verified = User::factory()->withProfile($this->college)->create(['first_name' => 'Mria', 'middle_name' => null, 'last_name' => 'Reyes']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('status', 'verified')
        ->assertSee('Correct name')
        ->call('confirmCorrectName', $verified->id)
        ->assertDispatched('modal-show', name: 'correct-name')
        ->assertSet('firstName', 'Mria')
        ->set('firstName', '')
        ->call('correctName')
        ->assertHasErrors(['firstName' => 'required'])
        ->set('firstName', 'Maria')
        ->set('middleName', 'Luz')
        ->call('correctName')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($verified->fresh())
        ->first_name->toBe('Maria')
        ->middle_name->toBe('Luz')
        ->identity_status->toBe(IdentityStatus::Verified);
    Log::shouldHaveReceived('info')->with('Requester name corrected', ['requester_id' => $verified->id, 'registrar_id' => $this->registrar->id]);
});

test('REQ-01-28 only a verified requester\'s name can be corrected, and only by a registrar', function () {
    $verified = User::factory()->withProfile($this->college)->create(['first_name' => 'Maria']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('confirmCorrectName', $this->student->id)
        ->assertForbidden();

    expect($this->student->can('correctName', $verified))->toBeFalse()
        ->and(User::factory()->departmentStaff()->create()->can('correctName', $verified))->toBeFalse()
        ->and($this->registrar->can('correctName', $verified))->toBeTrue();
});

test('REQ-01-29 when the ID cannot be stored nothing is saved and the error shows on the form', function () {
    $this->student->forceFill(['first_name' => 'Old', 'privacy_consented_at' => null])->save();
    $this->mock(SubmitIdentityDocument::class, fn ($mock) => $mock->shouldReceive('handle')
        ->andThrow(ValidationException::withMessages(['idFile' => 'The file could not be saved. Please try again.'])));

    reviewProfile(['form.privacy_consent' => true])
        ->call('save')
        ->assertHasErrors('form.id_file')
        ->assertSee('The file could not be saved. Please try again.')
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertNotDispatched('notify')
        ->assertSet('reviewing', false)
        ->assertSet('form.first_name', 'Juan');

    expect($this->student->fresh())
        ->first_name->toBe('Old')
        ->privacy_consented_at->toBeNull()
        ->identity_status->toBe(IdentityStatus::Unsubmitted);
});

test('REQ-01-16 the contact number field accepts digits only', function () {
    $this->actingAs($this->student)->get(route('student.profile'))
        ->assertOk()
        ->assertSeeHtml('x-mask="99999999999"');
});

test('REQ-01-25 the summary shows everything entered with the data privacy notice', function () {
    $this->student->forceFill(['privacy_consented_at' => null])->save();

    reviewProfile(['form.student_number' => '19-0001-123', 'form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg')])
        ->assertSee('Step 2 of 2: Review and confirm')
        ->assertSeeInOrder([
            'Juan', 'Not provided', 'Dela Cruz', '09171234567',
            'Graduate', 'College of Computer Studies', 'BS Computer Science', '19-0001-123', '2019 to 2023',
            'Passport: passport.jpg',
            'Data Privacy Act of 2012 (RA 10173)', 'I agree to the processing of my personal data',
            'Confirm and save',
        ]);
});

test('REQ-01-25 the summary shows the ID on file', function () {
    IdentityDocument::factory()->for($this->student)->create(['id_type' => 'UMID', 'original_name' => 'umid.jpg']);
    $this->student->forceFill(['identity_status' => IdentityStatus::Pending])->save();

    reviewProfile()->assertSee('UMID: umid.jpg');
});

test('REQ-01-25 a requester who already consented sees when, and no checkbox', function () {
    $this->student->forceFill(['privacy_consented_at' => '2026-10-27 09:00:00'])->save();

    reviewProfile()
        ->assertSee('You agreed to the processing of your personal data on Oct 27, 2026.')
        ->assertDontSee('I agree to the processing of my personal data');
});

test('REQ-01-25 going back keeps every entry and the chosen file', function () {
    $file = UploadedFile::fake()->image('passport.jpg');

    reviewProfile(['form.id_type' => 'Passport', 'form.id_file' => $file, 'form.privacy_consent' => true])
        ->call('back')
        ->assertSet('reviewing', false)
        ->assertSet('form.first_name', 'Juan')
        ->assertSet('form.id_type', 'Passport')
        ->call('review')
        ->assertSee('Passport: passport.jpg')
        ->call('save')
        ->assertHasNoErrors();

    expect(IdentityDocument::count())->toBe(1);
});

test('REQ-01-25 saving is refused unless the requester is on the summary', function () {
    $component = Livewire::actingAs($this->student)->test(Profile::class)
        ->set('form.first_name', 'Skipped')
        ->call('save');

    expect($this->student->fresh()->first_name)->not->toBe('Skipped');
    $component->assertNotDispatched('notify')->assertSet('reviewing', false);
});

test('REQ-01-25 the requester cannot jump to the summary from the browser', function () {
    Livewire::actingAs($this->student)->test(Profile::class)->set('reviewing', true);
})->throws(CannotUpdateLockedPropertyException::class);

test('REQ-01-25 the missing consent stays on the summary; an entry that fails on saving returns to the form', function () {
    $this->student->forceFill(['privacy_consented_at' => null])->save();

    $component = reviewProfile()
        ->call('save')
        ->assertHasErrors(['form.privacy_consent' => 'accepted'])
        ->assertSet('reviewing', true);

    $component->set('form.privacy_consent', true)
        ->set('form.contact_number', '123')
        ->call('save')
        ->assertHasErrors(['form.contact_number' => 'regex'])
        ->assertSet('reviewing', false);

    expect($this->student->fresh()->privacy_consented_at)->toBeNull();
});

test('REQ-01-25 confirming saves and returns to the form with the chosen ID cleared', function () {
    reviewProfile(['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg'), 'form.privacy_consent' => true])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('reviewing', false)
        ->assertSet('form.id_file', null)
        ->assertSet('form.id_type', '');
});

test('REQ-01-22 a requester with no ID pending or verified must give one to continue', function () {
    reviewProfile(['form.id_type' => '', 'form.id_file' => null])
        ->assertHasErrors(['form.id_type' => 'required', 'form.id_file' => 'required'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);

    $this->student->forceFill(['identity_status' => IdentityStatus::Rejected])->save();
    reviewProfile(['form.id_type' => '', 'form.id_file' => null])->assertHasErrors(['form.id_file' => 'required']);
});

test('REQ-01-22 a requester with an ID pending or verified needs no new one', function () {
    foreach ([IdentityStatus::Pending, IdentityStatus::Verified] as $status) {
        $this->student->forceFill(['identity_status' => $status])->save();

        reviewProfile()->assertHasNoErrors()->assertSet('reviewing', true);
    }
});

test('REQ-01-17 a requester can upload a valid ID, stored privately and pending verification', function () {
    $document = uploadId();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($document->path);
    expect($document->path)->toStartWith("identity-documents/{$this->student->id}/")
        ->and($document->status)->toBe(ReviewStatus::Pending)
        ->and($document->id_type)->toBe('Passport')
        ->and($this->student->fresh()->identity_status)->toBe(IdentityStatus::Pending);

    uploadId();
    expect($this->student->identityDocuments()->count())->toBe(2);
});

test('REQ-01-17 the ID must be an image or PDF of at most 5 MB with a known type', function () {
    fillProfile(['form.id_type' => 'Library card', 'form.id_file' => UploadedFile::fake()->create('id.exe', 100)])
        ->assertHasErrors(['form.id_type' => 'in', 'form.id_file' => 'mimes']);
    fillProfile(['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('big.jpg')->size(6000)])
        ->assertHasErrors(['form.id_file' => 'max']);
    fillProfile(['form.id_type' => '', 'form.id_file' => UploadedFile::fake()->image('id.jpg')])
        ->assertHasErrors(['form.id_type' => 'required']);

    expect(IdentityDocument::count())->toBe(0);
});

test('REQ-12-07 the program dropdown lists the chosen college\'s active programs, sorted', function () {
    Program::factory()->for($this->college, 'college')->create(['name' => 'Another Program']);
    Program::factory()->for($this->college, 'college')->inactive()->create(['name' => 'Closed Program']);
    Program::factory()->create(['name' => 'Foreign Program']);

    Livewire::actingAs($this->student)->test(Profile::class)
        ->assertSee('Choose a college first')
        ->set('form.college_id', $this->college->id)
        ->assertSeeInOrder(['Another Program', 'BS Computer Science'])
        ->assertDontSee('Closed Program')
        ->assertDontSee('Foreign Program')
        ->assertDontSee('Choose a college first');
});

test('REQ-12-07 choosing another college clears the program', function () {
    $other = Department::factory()->college()->create();

    Livewire::actingAs($this->student)->test(Profile::class)
        ->set('form.college_id', $this->college->id)
        ->set('form.program', 'BS Computer Science')
        ->set('form.college_id', $other->id)
        ->assertSet('form.program', '');
});

test('REQ-12-08 only an active program of the chosen college is accepted', function () {
    $other = Program::factory()->create(['name' => 'Foreign Program']);
    Program::factory()->for($this->college, 'college')->inactive()->create(['name' => 'Closed Program']);

    reviewProfile(['form.program' => 'Foreign Program'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.program' => 'Closed Program'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.program' => 'Typed by hand'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.college_id' => $other->department_id, 'form.program' => 'Foreign Program'])->assertHasNoErrors();
});

test('REQ-12-09 a saved program no longer on the list stays on the profile until the next save', function () {
    $student = User::factory()->withProfile($this->college)->create(['program' => 'Old Program']);

    expect($student->fresh()->missingProfileItems())->not->toContain('Academic information');

    $this->actingAs($student)->get(route('student.requests.create'))->assertSee('Old Program');
    Livewire::actingAs($student)->test(Profile::class)
        ->assertSet('form.program', 'Old Program')
        ->call('review')
        ->assertHasErrors(['form.program' => 'exists']);
    expect($student->fresh()->program)->toBe('Old Program');
});

test('REQ-12-10 a college without programs tells the requester to contact the Registrar', function () {
    $empty = Department::factory()->college()->create();

    Livewire::actingAs($this->student)->test(Profile::class)
        ->set('form.college_id', $empty->id)
        ->assertSee('No programs are listed for this college yet');
});

test('REQ-01-18 the new-request page shows, locked, with a red banner listing what the profile is missing', function () {
    $this->student->forceFill(['first_name' => null, 'last_name' => null])->save();

    $this->actingAs($this->student)->get(route('student.requests.create'))
        ->assertOk()
        ->assertSee('Verify your profile before making a request')
        ->assertSeeInOrder(['Full name', 'Contact number', 'Academic information', 'Valid ID', 'Data privacy consent'])
        ->assertSee('Complete my profile')
        ->assertSee('Request a credential')
        ->assertSee(' inert', false);

    $complete = User::factory()->withProfile($this->college)->create(['identity_status' => IdentityStatus::Unsubmitted]);
    $this->actingAs($complete)->get(route('student.requests.create'))
        ->assertSee('Verify your profile before making a request')
        ->assertDontSee('Academic information')
        ->assertSee('Valid ID');

    uploadId();
    $this->actingAs($this->student->fresh())->get(route('student.requests.create'))
        ->assertOk()
        ->assertDontSee('Verify your profile before making a request')
        ->assertDontSee(' inert', false);
});

test('REQ-01-18 a requester with an incomplete profile cannot submit a request', function () {
    Livewire::actingAs($this->student)->test(Create::class)
        ->set('form.credential_type_id', 1)
        ->set('form.purpose', 'Employment')
        ->call('submit')
        ->assertDispatched('notify');

    expect(CredentialRequest::count())->toBe(0);
});

test('REQ-01-18 a rejected ID is listed as missing, with the registrar\'s reason', function () {
    $student = User::factory()->withProfile($this->college)->create(['identity_status' => IdentityStatus::Rejected]);
    IdentityDocument::factory()->for($student)->create(['status' => ReviewStatus::Rejected, 'remarks' => 'The photo is blurred.']);

    $this->actingAs($student)->get(route('student.requests.create'))
        ->assertOk()
        ->assertSee('Verify your profile before making a request')
        ->assertSee('The photo is blurred.');
});

test('REQ-01-18 My Requests shows the same red banner until the profile is complete', function () {
    $this->actingAs($this->student)->get(route('student.dashboard'))->assertSee('Verify your profile before making a request');
    $this->actingAs(User::factory()->withProfile($this->college)->create())->get(route('student.dashboard'))
        ->assertDontSee('Verify your profile before making a request');
});

test('REQ-01-10 requesters land on the new-request page', function () {
    $this->actingAs($this->student)->get('/dashboard')->assertRedirect(route('student.requests.create'));
});

test('REQ-01-19 the registrar sees pending IDs first and can verify one', function () {
    Notification::fake();
    $document = uploadId();
    $verified = User::factory()->withProfile($this->college)->create(['first_name' => 'Already', 'middle_name' => null, 'last_name' => 'Verified']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->assertSee($this->student->name)
        ->assertDontSee('Already Verified')
        ->call('verify', $document->id)
        ->assertDispatched('notify');

    expect($document->fresh())
        ->status->toBe(ReviewStatus::Verified)
        ->reviewed_by->toBe($this->registrar->id)
        ->and($this->student->fresh())
        ->identity_status->toBe(IdentityStatus::Verified)
        ->identity_verified_at->not->toBeNull();

    Notification::assertSentTo($this->student, IdentityReviewed::class, fn ($n, array $channels) => in_array('mail', $channels, true));
    expect($verified->identity_status)->toBe(IdentityStatus::Verified);
});

test('REQ-01-19 rejecting needs a reason; the requester is told and can upload again', function () {
    Notification::fake();
    $document = uploadId();

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('confirmReject', $document->id)
        ->call('reject')
        ->assertHasErrors(['remarks' => 'required'])
        ->set('remarks', 'The photo is blurred.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($document->fresh()->status)->toBe(ReviewStatus::Rejected)
        ->and($this->student->fresh()->identity_status)->toBe(IdentityStatus::Rejected);
    Notification::assertSentTo($this->student, IdentityReviewed::class);

    $this->actingAs($this->student)->get(route('student.profile'))->assertSee('The photo is blurred.');
    uploadId($this->student->fresh());
    expect($this->student->fresh()->identity_status)->toBe(IdentityStatus::Pending);
});

test('REQ-01-19 only registrars can review IDs', function () {
    $document = uploadId();

    $this->actingAs($this->student)->get(route('registrar.identities'))->assertForbidden();
    Livewire::actingAs(User::factory()->departmentStaff()->create())->test(Index::class)->assertForbidden();

    expect($document->fresh()->status)->toBe(ReviewStatus::Pending);
});

test('REQ-01-20 only the owner and registrars can open an ID, and it is not cached', function () {
    $document = uploadId();

    $this->actingAs($this->student)->get(route('identity-documents.show', $document))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->registrar)->get(route('identity-documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->student()->create())->get(route('identity-documents.show', $document))->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('identity-documents.show', $document))->assertNotFound();
});

test('REQ-01-21 the new-request form takes the academic details from the profile', function () {
    $student = User::factory()->withProfile($this->college)->create(['student_number' => '19-0001-123', 'program' => 'BS Information Technology']);

    Livewire::actingAs($student)->test(Create::class)
        ->assertSee('Taken from your profile')
        ->assertSee('College of Computer Studies')
        ->assertSee('BS Information Technology')
        ->assertSee('19-0001-123');
});

test('My Profile shows the step label only on the summary; while editing it is for screen readers', function () {
    Livewire::actingAs($this->student)->test(Profile::class)
        ->assertSeeHtml('class="text-sm font-semibold text-ink outline-none sr-only"');

    reviewProfile()
        ->assertSee('Step 2 of 2: Review and confirm')
        ->assertDontSeeHtml('outline-none sr-only');
});
