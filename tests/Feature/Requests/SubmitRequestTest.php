<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->student = User::factory()->student()->create();
});

function fillRequestForm(array $overrides = []): array
{
    return array_merge([
        'form.credential_type_id' => test()->org->tor->id,
        'form.copies' => 2,
        'form.purpose' => 'Employment abroad',
        'form.student_number' => '19-0001-123',
        'form.college_id' => test()->org->ccs->id,
        'form.program' => 'BS Computer Science',
        'form.student_status' => 'alumni',
        'form.last_school_year' => '2022-2023',
    ], $overrides);
}

test('REQ-04-01 a student can submit a request with all details', function () {
    Livewire::actingAs($this->student)->test(Create::class)
        ->set(fillRequestForm())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', CredentialRequest::sole()));

    expect(CredentialRequest::sole())
        ->user_id->toBe($this->student->id)
        ->credential_type_id->toBe($this->org->tor->id)
        ->college_id->toBe($this->org->ccs->id)
        ->copies->toBe(2)
        ->student_number->toBe('19-0001-123')
        ->last_school_year->toBe('2022-2023');
});

test('REQ-04-01 invalid request details are rejected', function (array $overrides, string $field) {
    Livewire::actingAs($this->student)->test(Create::class)
        ->set(fillRequestForm($overrides))
        ->call('submit')
        ->assertHasErrors($field);

    expect(CredentialRequest::count())->toBe(0);
})->with([
    'too many copies' => [['form.copies' => 11], 'form.copies'],
    'non-numeric copies' => [['form.copies' => 'two'], 'form.copies'],
    'non-numeric college' => [['form.college_id' => 'abc'], 'form.college_id'],
    'bad school year format' => [['form.last_school_year' => '2023'], 'form.last_school_year'],
    'non-consecutive school year' => [['form.last_school_year' => '2020-2023'], 'form.last_school_year'],
    'office instead of college' => [fn () => ['form.college_id' => test()->org->library->id], 'form.college_id'],
    'unknown status' => [['form.student_status' => 'visitor'], 'form.student_status'],
    'missing purpose' => [['form.purpose' => ''], 'form.purpose'],
]);

test('REQ-04-01 inactive credential types, types without workflow and inactive colleges are refused', function () {
    $inactive = CredentialType::factory()->inactive()->withSteps([$this->org->registrarOffice])->create();
    $noWorkflow = CredentialType::factory()->create();
    $closedCollege = Department::factory()->college()->inactive()->create();

    foreach ([$inactive, $noWorkflow] as $type) {
        Livewire::actingAs($this->student)->test(Create::class)
            ->set(fillRequestForm(['form.credential_type_id' => $type->id]))
            ->call('submit')
            ->assertHasErrors('form.credential_type_id');
    }

    Livewire::actingAs($this->student)->test(Create::class)
        ->set(fillRequestForm(['form.college_id' => $closedCollege->id]))
        ->call('submit')
        ->assertHasErrors('form.college_id');
});

test('REQ-04-01 only verified students can open the request form', function () {
    $this->actingAs($this->student)->get(route('student.requests.create'))->assertOk()->assertSee('Transcript of Records');
    $this->actingAs(User::factory()->unverified()->create())->get(route('student.requests.create'))->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->registrar()->create())->get(route('student.requests.create'))->assertForbidden();
});

test('REQ-04-02 submitted requests get a reference number and a fee snapshot', function () {
    $request = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    $this->org->tor->update(['fee' => '999.00']);

    expect($request->fresh())
        ->reference_no->toBe(sprintf('CR-%s-%06d', now()->year, $request->id))
        ->requires_payment->toBeTrue()
        ->fee->toBe('300.00');
});

test('REQ-04-03 paid requests await payment and free requests go to registrar review', function () {
    expect(submitRequest($this->student, $this->org->tor, $this->org->ccs)->status)->toBe(RequestStatus::AwaitingPayment)
        ->and(submitRequest($this->student, $this->org->free, $this->org->ccs)->status)->toBe(RequestStatus::RegistrarReview);
});

test('REQ-04-03 submission is recorded in the ledger', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);

    expect($request->events()->sole())
        ->type->toBe(RequestEventType::Submitted)
        ->actor_id->toBe($this->student->id)
        ->to_status->toBe(RequestStatus::RegistrarReview);
});

test('REQ-04-04 students see only their own requests, newest first', function () {
    $first = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $second = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    $other = submitRequest(User::factory()->student()->create(), $this->org->free, $this->org->cba);

    $this->actingAs($this->student)->get(route('student.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([$second->reference_no, $first->reference_no])
        ->assertDontSee($other->reference_no);

    $this->actingAs($this->student)->get(route('student.requests.show', $other))->assertNotFound();
    $this->actingAs($this->student)->get(route('student.requests.show', $first))->assertOk()->assertSee($first->reference_no);
});
