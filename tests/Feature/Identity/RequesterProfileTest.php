<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Livewire\Student\Profile;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Settings → Profile (REQ-15-07): the saved details and valid ID, with the profile rules of spec 01
| and the program list of spec 12. New Request uses the same form (tests/Feature/Requests/RequestIdentityTest.php).
*/

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

    // Without a saved ID one is required (REQ-15-04).
    $id = $user->latestIdentityDocument()->exists()
        ? []
        : ['form.id_type' => 'Passport', 'form.id_file' => UploadedFile::fake()->image('passport.jpg')];

    foreach (array_merge([
        'form.first_name' => 'Juan',
        'form.middle_name' => '',
        'form.last_name' => 'Dela Cruz',
        'form.sex' => 'male',
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

test('REQ-15-07 staff get 403 on Settings → Profile', function () {
    $this->actingAs($this->registrar)->get(route('settings.profile'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('settings.profile'))->assertForbidden();
});

test('REQ-15-07 the profile is never locked: a requester with requests can still change everything, the ID included', function () {
    $student = User::factory()->withProfile($this->college)->create(['first_name' => 'Maria']);

    $this->actingAs($student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('wire:model.live.blur="form.first_name"')
        ->assertSeeHtml('data-remove-saved-id')
        ->assertSee('Your Saved ID');

    Livewire::actingAs($student)->test(Profile::class)->call('removeSavedId');

    fillProfile(['form.first_name' => 'Mariel', 'form.id_type' => 'UMID', 'form.id_file' => UploadedFile::fake()->image('umid.jpg')], $student)
        ->assertHasNoErrors();

    expect($student->fresh()->first_name)->toBe('Mariel')
        ->and($student->identityDocuments()->count())->toBe(1)
        ->and($student->latestIdentityDocument()->first()->id_type)->toBe('UMID');
});

test('REQ-15-18 removing with no saved ID does nothing, and only the requester\'s own ID is ever removed', function () {
    $other = IdentityDocument::factory()->for(User::factory()->student())->create();

    Livewire::actingAs($this->student)->test(Profile::class)->call('removeSavedId')->assertHasNoErrors();

    expect($other->fresh())->not->toBeNull()
        ->and($other->fresh()->removed_at)->toBeNull();
});

test('REQ-01-16 a requester can save their academic information; the student number is optional', function () {
    fillProfile()->assertHasNoErrors();

    expect($this->student->fresh())
        ->academic_status->toBe(AcademicStatus::Graduate)
        ->college_id->toBe($this->college->id)
        ->program->toBe('BS Computer Science')
        ->student_number->toBeNull()
        ->attended_from->toBe(2019)
        ->attended_to->toBe(2023)
        ->contact_number->toBe('+639171234567');   // E.164 (REQ-36-01)
});

test('REQ-01-16 the years attended and college are validated', function () {
    $office = Department::factory()->create();

    fillProfile(['form.attended_from' => 2024, 'form.attended_to' => 2020])->assertHasErrors(['form.attended_to' => 'gte']);
    fillProfile(['form.attended_from' => 1900])->assertHasErrors(['form.attended_from' => 'between']);
    fillProfile(['form.attended_to' => now()->year + 1])->assertHasErrors(['form.attended_to' => 'between']);
    fillProfile(['form.college_id' => $office->id])->assertHasErrors(['form.college_id' => 'exists']);
});

test('REQ-01-29 one save stores the name, contact, academic information, consent and ID together', function () {
    $this->student->forceFill(['first_name' => 'Old', 'last_name' => 'Name', 'privacy_consented_at' => null])->save();

    fillProfile(['form.middle_name' => 'Santos'])->assertHasNoErrors()->assertDispatched('notify');

    expect($this->student->fresh())
        ->first_name->toBe('Juan')
        ->middle_name->toBe('Santos')
        ->last_name->toBe('Dela Cruz')
        ->privacy_consented_at->not->toBeNull()
        ->and($this->student->identityDocuments()->count())->toBe(1);
});

test('REQ-15-07 every save needs the data privacy consent ticked; the first consent\'s time is kept', function () {
    $this->student->forceFill(['privacy_consented_at' => null])->save();

    fillProfile(['form.privacy_consent' => false])->assertHasErrors(['form.privacy_consent' => 'accepted']);
    expect($this->student->fresh()->privacy_consented_at)->toBeNull();

    fillProfile()->assertHasNoErrors()->assertSet('form.privacy_consent', false);   // unticked again for the next save
    $consentedAt = $this->student->fresh()->privacy_consented_at;

    fillProfile(['form.privacy_consent' => false])->assertHasErrors(['form.privacy_consent' => 'accepted']);
    fillProfile()->assertHasNoErrors();
    expect($this->student->fresh()->privacy_consented_at->equalTo($consentedAt))->toBeTrue();
});

test('REQ-01-26 a type of ID needs a file, and a file needs a type', function () {
    reviewProfile(['form.id_file' => null])
        ->assertHasErrors('form.id_file')
        ->assertHasNoErrors('form.id_type')
        ->assertDispatched('modal-show', name: 'profile-errors');
    reviewProfile(['form.id_type' => ''])
        ->assertHasErrors('form.id_type')
        ->assertHasNoErrors('form.id_file');
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
        ->and(IdentityDocument::count())->toBe(0);
});

test('REQ-15-07 saving is refused unless the requester is on the summary', function () {
    $component = Livewire::actingAs($this->student)->test(Profile::class)
        ->set('form.first_name', 'Skipped')
        ->call('save');

    expect($this->student->fresh()->first_name)->not->toBe('Skipped');
    $component->assertNotDispatched('notify')->assertSet('reviewing', false);
});

test('REQ-15-07 the requester cannot jump to the summary from the browser', function () {
    Livewire::actingAs($this->student)->test(Profile::class)->set('reviewing', true);
})->throws(CannotUpdateLockedPropertyException::class);

test('REQ-15-04 a requester with no saved ID must give one; with one, none is needed', function () {
    reviewProfile(['form.id_type' => '', 'form.id_file' => null])
        ->assertHasErrors(['form.id_type' => 'required', 'form.id_file' => 'required'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);

    IdentityDocument::factory()->for($this->student)->create();
    reviewProfile()->assertHasNoErrors()->assertSet('reviewing', true);
});

test('REQ-01-17 an uploaded ID is stored privately and becomes the saved ID', function () {
    fillProfile(['form.id_type' => 'UMID', 'form.id_file' => UploadedFile::fake()->image('umid.jpg')])->assertHasNoErrors();

    $saved = $this->student->latestIdentityDocument()->firstOrFail();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($saved->path);
    expect($saved->path)->toStartWith("identity-documents/{$this->student->id}/")
        ->and($saved->id_type)->toBe('UMID')
        ->and($this->student->identityDocuments()->count())->toBe(1);
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

test('REQ-12-08 only an active program of the chosen college is accepted', function () {
    $other = Program::factory()->create(['name' => 'Foreign Program']);
    Program::factory()->for($this->college, 'college')->inactive()->create(['name' => 'Closed Program']);

    reviewProfile(['form.program' => 'Foreign Program'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.program' => 'Closed Program'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.program' => 'Typed by hand'])->assertHasErrors(['form.program' => 'exists']);
    reviewProfile(['form.college_id' => $other->department_id, 'form.program' => 'Foreign Program'])->assertHasNoErrors();
});

test('REQ-01-20 only the owner and registrars can open an ID, and it is not cached', function () {
    fillProfile()->assertHasNoErrors();
    $document = $this->student->latestIdentityDocument()->firstOrFail();

    $this->actingAs($this->student)->get(route('identity-documents.show', $document))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->registrar)->get(route('identity-documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->student()->create())->get(route('identity-documents.show', $document))->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('identity-documents.show', $document))->assertNotFound();
});

test('REQ-36-01 the contact number is a mobile number of the chosen country, saved in E.164 form', function () {
    fillProfile(['form.contact_country' => 'US', 'form.contact_number' => '(650) 253-0000'])->assertHasNoErrors();
    expect($this->student->fresh()->contact_number)->toBe('+16502530000');

    fillProfile(['form.contact_country' => 'PH', 'form.contact_number' => '0917 123 4567'])->assertHasNoErrors();
    expect($this->student->fresh()->contact_number)->toBe('+639171234567');

    // The Philippines by default, for a requester with no number yet.
    expect(Livewire::actingAs(User::factory()->student()->create())->test(Profile::class)->get('form.contact_country'))->toBe('PH');

    foreach (['0917123456', '02 8888 8888', 'abc'] as $number) {   // too short, a landline, not a number
        fillProfile(['form.contact_number' => $number])->assertHasErrors('form.contact_number');
    }
    fillProfile(['form.contact_country' => 'US', 'form.contact_number' => '09171234567'])->assertHasErrors('form.contact_number');
    fillProfile(['form.contact_country' => 'XX'])->assertHasErrors('form.contact_country');
});

test('REQ-36-01 a saved number comes back as its country and number', function () {
    $this->student->forceFill(['contact_number' => '+16502530000'])->save();

    Livewire::actingAs($this->student)->test(Profile::class)
        ->assertSet('form.contact_country', 'US')
        ->assertSet('form.contact_number', '6502530000');
});
