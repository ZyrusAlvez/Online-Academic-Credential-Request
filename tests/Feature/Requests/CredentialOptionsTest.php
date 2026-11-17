<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Exceptions\InvalidTransition;
use App\Livewire\Admin\CredentialTypes\Index;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\CredentialTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Spec 29: the credential list changes and the per-type options: the terms and academic year, a fee
| per term and the statuses a credential is free for.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);   // a graduate (UserFactory::withProfile)
    $this->year = CredentialType::academicYears()[1];

    $this->semestral = CredentialType::factory()->paid('100.00')
        ->withSteps([[$this->org->registrarOffice, RouteTask::Prepare]])
        ->create(['name' => 'Term Report', 'asks_term_and_year' => true, 'fee_per_term' => true]);
    $this->grades = CredentialType::factory()->paid('230.00')
        ->withSteps([[$this->org->registrarOffice, RouteTask::Prepare]])
        ->create(['name' => 'Grades Certificate', 'free_for' => [AcademicStatus::Graduate->value]]);
});

/** New Request for the owner with one credential, then reviewed. */
function newRequestFor(CredentialType $type, array $fields = []): Testable
{
    $page = Livewire::actingAs(test()->owner)->test(Create::class)
        ->set('form.credential_type_id', $type->id)
        ->set('form.purpose', 'Employment');

    foreach ($fields as $field => $value) {
        $page->set("form.{$field}", $value);
    }

    return $page->call('review');
}

/** A Semestral Report submitted through the workflow. */
function semestral(User $owner, array $terms, string $year): CredentialRequest
{
    return submitRequest($owner, test()->semestral, ['terms' => $terms, 'academic_year' => $year]);
}

test('REQ-29-01 the seeded list drops Certification – General and renames Copy of Grades and Transfer Certificate', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);

    $names = CredentialType::pluck('name');

    expect($names)->not->toContain('Certification – General', 'Copy of Grades', 'Transfer Certificate')
        ->and($names)->toContain('Certificate of Grades', 'Certificate of Transfer')
        ->and(CredentialType::where('name', 'Certificate of Grades')->sole()->free_for)->toBe(['graduate'])
        ->and(CredentialType::where('name', 'Certificate of Transfer')->sole()->requires_receiving_institution)->toBeTrue();
});

test('REQ-29-02 Semestral Report of Grades is seeded at ₱100 per term, asking for the terms and year', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);

    $type = CredentialType::where('name', 'Semestral Report of Grades')->with('steps.department')->sole();

    expect($type)
        ->fee->toBe('100.00')
        ->fee_per_term->toBeTrue()
        ->asks_term_and_year->toBeTrue()
        ->processing_days->toBe(6)
        ->and($type->routeSummary())->toBe('REG → VPF → CHAN');
});

test('REQ-29-03 the Admin sets the term-and-year, per-term fee and Free for options', function () {
    $admin = User::factory()->admin()->create();
    $type = CredentialType::factory()->paid('230.00')->create(['name' => 'Some Report']);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('edit', $type->id)
        ->assertSeeHtml('data-fee-per-term')->assertSeeHtml('data-free-for')
        ->set('form.asks_term_and_year', true)
        ->set('form.fee_per_term', true)
        ->set('form.free_for', ['graduate', 'former'])
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh())->asks_term_and_year->toBeTrue()->fee_per_term->toBeTrue()->free_for->toBe(['graduate', 'former']);

    // The fee options go with the fee.
    Livewire::actingAs($admin)->test(Index::class)
        ->call('edit', $type->id)
        ->set('form.requires_payment', false)
        ->assertDontSeeHtml('data-free-for')
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh())->fee_per_term->toBeFalse()->free_for->toBeNull()->asks_term_and_year->toBeTrue();

    Livewire::actingAs($admin)->test(Index::class)
        ->call('edit', $type->id)->set('form.requires_payment', true)->set('form.free_for', ['alumni'])->call('save')
        ->assertHasErrors(['form.free_for.0']);
});

test('REQ-29-04 a credential that asks for them shows and requires the terms and academic year, and the request keeps them', function () {
    newRequestFor($this->org->tor)->assertDontSeeHtml('data-term-and-year');

    newRequestFor($this->semestral)
        ->assertSeeHtml('data-term-and-year')
        ->assertSee(['1st Semester', '2nd Semester', 'Summer', 'Academic year'])
        ->assertHasErrors(['form.terms' => 'required', 'form.academic_year' => 'required']);

    newRequestFor($this->semestral, ['terms' => ['first', 'summer'], 'academic_year' => '1900-1901'])->assertHasErrors(['form.academic_year']);

    newRequestFor($this->semestral, ['terms' => ['first', 'summer'], 'academic_year' => $this->year])
        ->assertHasNoErrors()
        ->assertSeeInOrder(['Terms', '1st Semester, Summer', 'Academic year', $this->year]);

    expect(semestral($this->owner, ['summer', 'first'], $this->year))
        ->terms->toBe(['first', 'summer'])
        ->academic_year->toBe($this->year)
        ->and(fn () => submitRequest($this->owner, $this->semestral))->toThrow(InvalidTransition::class, 'terms');

    // Other credentials keep neither.
    expect(submitRequest($this->owner, $this->org->free, ['terms' => ['first'], 'academic_year' => $this->year]))
        ->terms->toBeNull()->academic_year->toBeNull();
});

test('REQ-29-05 a fee per term is charged once per ticked term', function () {
    $request = semestral($this->owner, ['first', 'second'], $this->year);

    expect($request)->fee->toBe('200.00')->amount_due->toBe('200.00')->requires_payment->toBeTrue();

    newRequestFor($this->semestral, ['terms' => ['first', 'second', 'summer'], 'academic_year' => $this->year])
        ->assertSeeInOrder(['data-summary-title', 'Fee', '300.00'], false);

    // A flat fee is charged once, whatever is ticked.
    $this->semestral->update(['fee_per_term' => false]);
    expect(semestral($this->owner, ['first', 'second'], CredentialType::academicYears()[2])->fee)->toBe('100.00');
});

test('REQ-29-06 a credential that asks for the year may come once per academic year in one application', function () {
    $years = CredentialType::academicYears();

    $lead = workflow()->submit($this->owner, [
        ['credential_type_id' => $this->semestral->id, 'purpose' => 'Employment', 'terms' => ['first'], 'academic_year' => $years[1]],
        ['credential_type_id' => $this->semestral->id, 'purpose' => 'Employment', 'terms' => ['first', 'second'], 'academic_year' => $years[2]],
    ]);

    expect($lead->amount_due)->toBe('300.00')
        ->and(fn () => workflow()->submit($this->owner, [
            ['credential_type_id' => $this->semestral->id, 'purpose' => 'Employment', 'terms' => ['first'], 'academic_year' => $years[1]],
            ['credential_type_id' => $this->semestral->id, 'purpose' => 'Employment', 'terms' => ['second'], 'academic_year' => $years[1]],
        ]))->toThrow(InvalidTransition::class, 'once per academic year');

    $page = Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->semestral->id)->set('form.purpose', 'Employment')
        ->set('form.terms', ['first'])->set('form.academic_year', $years[1])
        ->call('addCredential')
        ->set('form.more.0.credential_type_id', $this->semestral->id)->set('form.more.0.purpose', 'Employment')
        ->set('form.more.0.terms', ['second'])->set('form.more.0.academic_year', $years[1])
        ->call('review')
        ->assertHasErrors(['form.more.0.academic_year' => 'not_in']);

    $page->set('form.more.0.academic_year', $years[2])->call('review')->assertHasNoErrors(['form.more.0.academic_year']);

    // Any other credential stays once per application (REQ-27-01).
    Livewire::actingAs($this->owner)->test(Create::class)
        ->set('form.credential_type_id', $this->org->tor->id)->set('form.purpose', 'Employment')
        ->call('addCredential')
        ->set('form.more.0.credential_type_id', $this->org->tor->id)->set('form.more.0.purpose', 'Employment')
        ->call('review')
        ->assertHasErrors(['form.more.0.credential_type_id' => 'not_in']);
});

test('REQ-29-07 a credential is free for the statuses it is free for, online, walk-in and on Edit Request', function () {
    // A graduate's Certificate of Grades costs nothing and skips payment.
    $free = evaluate(submitRequest($this->owner, $this->grades), $this->registrar);

    expect($free->fresh())->fee->toBeNull()->requires_payment->toBeFalse()->status->toBe(RequestStatus::AcceptedForProcessing);

    newRequestFor($this->grades)->assertSeeInOrder(['Fee', 'None']);

    // A currently enrolled student pays the fee.
    $this->owner->update(['academic_status' => AcademicStatus::Current]);
    expect(submitRequest($this->owner, $this->grades))->fee->toBe('230.00')->requires_payment->toBeTrue();

    // Walk-in: priced for the client's status.
    $walkIn = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->grades->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'sex' => 'male', 'maiden_name' => null,
        'email' => 'pedro@example.com', 'contact_number' => '09171234567', 'academic_status' => 'graduate',
        'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science', 'student_number' => null,
        'attended_from' => 2018, 'attended_to' => 2022,
    ]);
    expect($walkIn->fresh())->fee->toBeNull()->status->toBe(RequestStatus::AcceptedForProcessing);

    // Edit Request: changing to the credential prices it for the owner's status.
    $this->owner->update(['academic_status' => AcademicStatus::Graduate]);
    $returned = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($returned, $this->registrar);
    workflow()->recordCheck($returned, $this->registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($returned, $this->registrar, 'Please choose the right credential.');
    workflow()->resubmit($returned, $this->owner, null, ['credential_type_id' => $this->grades->id, 'purpose' => 'Employment']);

    expect($returned->fresh())->credential_type_id->toBe($this->grades->id)->fee->toBeNull()->requires_payment->toBeFalse();
});

test('REQ-29-08 the terms, year and fee options are shown with the credential', function () {
    $html = Livewire::actingAs($this->owner)->test(Create::class)->html();

    expect(credentialCard($html, $this->semestral))->toContain('100.00')->toContain('per term')
        ->and(credentialCard($html, $this->grades))->toContain('Free for graduates')
        ->and(credentialCard($html, $this->org->tor))->not->toContain('Free for');

    $request = semestral($this->owner, ['second'], $this->year);

    $this->actingAs($this->owner)->get(route('student.requests.show', $request))->assertOk()
        ->assertSeeInOrder(['Terms', '2nd Semester', 'Academic year', $this->year]);

    workflow()->startEvaluation($request, $this->registrar);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->assertSeeInOrder(['Terms', '2nd Semester', 'Academic year', $this->year]);
});
