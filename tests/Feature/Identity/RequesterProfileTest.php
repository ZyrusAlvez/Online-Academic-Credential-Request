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
use Illuminate\Support\Str;
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

test('REQ-15-07 requesters have a Settings → Profile page, first in their settings and where /settings takes them', function () {
    $this->actingAs($this->student)->get('/settings')->assertRedirect(route('settings.profile'));

    $this->actingAs($this->student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeInOrder(['Profile', 'Email notifications', 'Password'])
        ->assertSee('Used to fill in your next request.')
        ->assertDontSee('Email address');
});

test('REQ-15-07 staff get 403 on Settings → Profile', function () {
    $this->actingAs($this->registrar)->get(route('settings.profile'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('settings.profile'))->assertForbidden();
});

test('REQ-15-07 the profile is never locked: a requester with requests can still change everything, the ID included', function () {
    $student = User::factory()->withProfile($this->college)->create(['first_name' => 'Maria']);

    $this->actingAs($student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('wire:model="form.first_name"')
        ->assertSeeHtml('wire:model="form.id_file"')
        ->assertSee('Your Saved ID');

    fillProfile(['form.first_name' => 'Mariel', 'form.id_type' => 'UMID', 'form.id_file' => UploadedFile::fake()->image('umid.jpg')], $student)
        ->assertHasNoErrors();

    expect($student->fresh()->first_name)->toBe('Mariel')
        ->and($student->identityDocuments()->count())->toBe(2)
        ->and($student->latestIdentityDocument()->first()->id_type)->toBe('UMID');
});

test('REQ-12-07 Settings → Profile labels the program dropdown "Program"', function () {
    $this->actingAs($this->student)->get(route('settings.profile'))
        ->assertOk()->assertSee('Program')->assertDontSee('Program or strand');
});

test('REQ-15-04 the chosen ID file name is a link that previews it in the file viewer', function () {
    $this->actingAs($this->student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('data-id-preview', false)
        ->assertSee("\$dispatch('open-file', chosen)", false);
});

test('REQ-15-05 the summary has a Summary heading and shows a chosen image ID as a picture that opens in the viewer', function () {
    $html = reviewProfile()->assertSee('Summary')->assertSeeHtml('data-summary-title')->html();

    expect($html)->toContain('data-id-summary-preview')
        ->and($html)->toMatch('#<img src="[^"]*preview-file[^"]*"[^>]*alt="Your chosen ID \(open larger\)"#')
        ->and($html)->toContain('passport.jpg');
});

test('REQ-15-05 a chosen PDF ID is shown on the summary as a link that opens it in the viewer', function () {
    $html = reviewProfile(['form.id_file' => UploadedFile::fake()->createWithContent('passport.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n")])
        ->assertSee('passport.pdf')->html();

    expect($html)->toContain('data-id-summary-preview')
        ->and($html)->toContain('preview-file')
        ->and($html)->toMatch('#application\\\\?/pdf#')
        ->and($html)->toContain('View PDF')
        ->and($html)->not->toContain('Your chosen ID (open larger)');
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
        ->contact_number->toBe('09171234567');
});

test('REQ-01-16 the contact number must be exactly 11 digits', function () {
    foreach (['0917 123 4567', '+639171234567', '0917123456', '091712345678', '0917-123-4567'] as $number) {
        fillProfile(['form.contact_number' => $number])->assertHasErrors(['form.contact_number' => 'regex']);
    }

    fillProfile(['form.contact_number' => '09171234567'])->assertHasNoErrors();
});

test('REQ-01-16 the contact number field accepts digits only', function () {
    $this->actingAs($this->student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('x-mask="99999999999"');
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

test('REQ-15-07 the profile form has a single button, which reviews instead of saving', function () {
    $html = $this->actingAs($this->student)->get(route('settings.profile'))->assertOk()->getContent();

    expect(substr_count($html, 'wire:submit'))->toBe(1)
        ->and($html)->toContain('>Confirm</')
        ->and($html)->not->toContain('Confirm and save')
        ->and($html)->not->toContain('Data Privacy Act');
});

test('REQ-15-07 reviewing saves nothing, and invalid entries stay on the form', function () {
    $this->student->forceFill(['first_name' => 'Old', 'privacy_consented_at' => null])->save();

    reviewProfile(['form.first_name' => ''])
        ->assertHasErrors(['form.first_name' => 'required'])
        ->assertSet('reviewing', false)
        ->assertDontSee('Confirm and save');

    reviewProfile()->assertHasNoErrors()->assertSet('reviewing', true);

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
    $this->actingAs($this->student)->get(route('settings.profile'))
        ->assertOk()
        ->assertSeeHtml('wire:submit="review" novalidate');
});

test('REQ-01-26 a type of ID needs a file, and a file needs a type', function () {
    IdentityDocument::factory()->for($this->student)->create();

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

test('REQ-15-05 the summary shows everything entered with the data privacy notice', function () {
    $this->student->forceFill(['privacy_consented_at' => null])->save();

    reviewProfile(['form.student_number' => '19-0001-123'])
        ->assertSeeInOrder([
            'Juan', 'Not provided', 'Dela Cruz', '09171234567',
            'Graduate', 'College of Computer Studies', 'BS Computer Science', '19-0001-123', '2019 to 2023',
            'Passport', 'passport.jpg',
            'Data Privacy Act of 2012 (RA 10173)', 'I agree to the processing of my personal data',
            'Confirm and save',
        ]);
});

test('REQ-15-05 the summary shows the saved ID when no other is chosen', function () {
    IdentityDocument::factory()->for($this->student)->create(['id_type' => 'UMID', 'original_name' => 'umid.jpg']);

    $summary = Str::after(reviewProfile()->html(), 'data-summary-title');

    expect($summary)->toMatch('#<dt class="text-zinc-600">Type of ID</dt>\s*<dd[^>]*>\s*UMID#')
        ->toContain('umid.jpg');
});

test('REQ-15-05 a requester who consented before still gets an unticked checkbox, and Confirm and save waits for it', function () {
    $this->student->forceFill(['privacy_consented_at' => '2026-10-27 09:00:00'])->save();

    $html = reviewProfile()
        ->assertSee('I agree to the processing of my personal data')
        ->assertDontSee('You agreed to the processing of your personal data')
        ->assertSet('form.privacy_consent', false)
        ->html();

    expect($html)->toMatch('/<button[^>]*x-bind:disabled="! \$wire\.form\.privacy_consent"[^>]*disabled[^>]*data-save-profile/')
        ->and(reviewProfile(['form.privacy_consent' => true])->html())->not->toMatch('/<button[^>]*\sdisabled="disabled"[^>]*data-save-profile/');
});

test('REQ-15-05 going back keeps every entry and the chosen file', function () {
    reviewProfile(['form.privacy_consent' => true])
        ->call('back')
        ->assertSet('reviewing', false)
        ->assertSet('form.first_name', 'Juan')
        ->assertSet('form.id_type', 'Passport')
        ->call('review')
        ->assertSeeInOrder(['Type of ID', 'Passport', 'New upload', 'passport.jpg'])
        ->call('save')
        ->assertHasNoErrors();

    expect(IdentityDocument::count())->toBe(1);
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

test('REQ-15-07 the missing consent stays on the summary; an entry that fails on saving returns to the form', function () {
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

test('REQ-15-07 confirming saves and returns to the form with the chosen ID cleared', function () {
    reviewProfile(['form.privacy_consent' => true])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('reviewing', false)
        ->assertSet('form.id_file', null)
        ->assertSet('form.id_type', '');
});

test('REQ-15-04 a requester with no saved ID must give one; with one, none is needed', function () {
    reviewProfile(['form.id_type' => '', 'form.id_file' => null])
        ->assertHasErrors(['form.id_type' => 'required', 'form.id_file' => 'required'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);

    IdentityDocument::factory()->for($this->student)->create();
    reviewProfile()->assertHasNoErrors()->assertSet('reviewing', true);
});

test('REQ-01-17 an uploaded ID is stored privately and becomes the saved ID; earlier uploads are kept', function () {
    fillProfile()->assertHasNoErrors();
    fillProfile(['form.id_type' => 'UMID', 'form.id_file' => UploadedFile::fake()->image('umid.jpg')])->assertHasNoErrors();

    $saved = $this->student->latestIdentityDocument()->firstOrFail();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($saved->path);
    expect($saved->path)->toStartWith("identity-documents/{$this->student->id}/")
        ->and($saved->id_type)->toBe('UMID')
        ->and($this->student->identityDocuments()->count())->toBe(2);
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

test('REQ-18-02 requesters land on the new-request page', function () {   // REQ-01-10, superseded by REQ-18-02/03
    $this->actingAs($this->student)->get('/dashboard')->assertRedirect(route('student.requests.create'));
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

test('REQ-15-07 the profile shows no step label; screen readers still hear which step it is', function () {
    Livewire::actingAs($this->student)->test(Profile::class)
        ->assertDontSee('Step 1 of 2')
        ->assertSeeHtml('<p id="profile-step" tabindex="-1" class="sr-only">');

    reviewProfile()
        ->assertDontSee('Step 2 of 2')
        ->assertSee('Review and confirm your details')
        ->assertSeeHtml('<p id="profile-step" tabindex="-1" class="sr-only">');
});
