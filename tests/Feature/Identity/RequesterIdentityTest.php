<?php

use App\Enums\AcademicStatus;
use App\Enums\IdentityStatus;
use App\Enums\ReviewStatus;
use App\Livewire\Registrar\Identities\Index;
use App\Livewire\Student\Profile;
use App\Livewire\Student\Requests\Create;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Notifications\IdentityReviewed;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->college = Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
});

function fillProfile(array $overrides = []): Testable
{
    $component = Livewire::actingAs(test()->student)->test(Profile::class);

    foreach (array_merge([
        'form.contact_number' => '+63 917 123 4567',
        'form.academic_status' => 'graduate',
        'form.college_id' => test()->college->id,
        'form.program' => 'BS Computer Science',
        'form.student_number' => '',
        'form.attended_from' => 2019,
        'form.attended_to' => 2023,
    ], $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('save');
}

function uploadId(?User $user = null): IdentityDocument
{
    Livewire::actingAs($user ?? test()->student)->test(Profile::class)
        ->set('idType', 'Passport')
        ->set('idFile', UploadedFile::fake()->image('passport.jpg'))
        ->call('uploadId')
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
        ->contact_number->toBe('+63 917 123 4567');
});

test('REQ-01-16 the years attended and college are validated', function () {
    $office = Department::factory()->create();

    fillProfile(['form.attended_from' => 2024, 'form.attended_to' => 2020])->assertHasErrors(['form.attended_to' => 'gte']);
    fillProfile(['form.attended_from' => 1900])->assertHasErrors(['form.attended_from' => 'between']);
    fillProfile(['form.attended_to' => now()->year + 1])->assertHasErrors(['form.attended_to' => 'between']);
    fillProfile(['form.college_id' => $office->id])->assertHasErrors(['form.college_id' => 'exists']);
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
    Livewire::actingAs($this->student)->test(Profile::class)
        ->set('idType', 'Library card')
        ->set('idFile', UploadedFile::fake()->create('id.exe', 100))
        ->call('uploadId')
        ->assertHasErrors(['idType' => 'in', 'idFile' => 'mimes']);

    Livewire::actingAs($this->student)->test(Profile::class)
        ->set('idType', 'Passport')
        ->set('idFile', UploadedFile::fake()->image('big.jpg')->size(6000))
        ->call('uploadId')
        ->assertHasErrors(['idFile' => 'max']);

    expect(IdentityDocument::count())->toBe(0);
});

test('REQ-01-18 requesters without a complete profile and an ID are sent to their profile', function () {
    $this->actingAs($this->student)->get(route('student.requests.create'))->assertRedirect(route('student.profile'));

    fillProfile();
    $this->actingAs($this->student)->get(route('student.requests.create'))->assertRedirect(route('student.profile'));

    uploadId();
    $this->actingAs($this->student->fresh())->get(route('student.requests.create'))->assertOk();
});

test('REQ-01-18 a rejected ID sends the requester back to their profile', function () {
    $student = User::factory()->withProfile($this->college)->create(['identity_status' => IdentityStatus::Rejected]);

    $this->actingAs($student)->get(route('student.requests.create'))->assertRedirect(route('student.profile'));
});

test('REQ-01-19 the registrar sees pending IDs first and can verify one', function () {
    Notification::fake();
    $document = uploadId();
    $verified = User::factory()->withProfile($this->college)->create(['first_name' => 'Already', 'last_name' => 'Verified']);

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
