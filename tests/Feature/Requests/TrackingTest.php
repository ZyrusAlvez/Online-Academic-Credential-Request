<?php

use App\Models\User;
use App\Services\RequestWorkflow;

beforeEach(function () {
    $this->org = organisation();
    $this->workflow = app(RequestWorkflow::class);
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Regina', 'middle_name' => null, 'last_name' => 'Registrar']);
    $this->librarian = User::factory()->departmentStaff($this->org->library)->create(['first_name' => 'Lina', 'middle_name' => null, 'last_name' => 'Librarian']);

    $this->request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->request->forceFill(['credential_type_id' => $this->org->tor->id])->save();
    $this->workflow->approve($this->request, $this->registrar);
    $step = $this->request->currentStep()->firstOrFail();
    $this->workflow->hold($step, $this->librarian, 'Unreturned book');
    $this->workflow->sign($step->fresh(), $this->librarian, 'Book returned');
});

test('REQ-06-01 the student sees a tracking history, newest first, with offices and remarks', function () {
    $this->actingAs($this->student)->get(route('student.requests.show', $this->request))
        ->assertOk()
        ->assertSee('Tracking history')
        ->assertSeeInOrder(['Signed by Library', 'Book returned', 'On hold at Library', 'Unreturned book', 'Approved and sent to the signing offices', 'Request submitted']);
});

test('REQ-06-02 the student history shows offices, not staff names', function () {
    $this->actingAs($this->student)->get(route('student.requests.show', $this->request))
        ->assertDontSee('Lina Librarian')
        ->assertDontSee('Regina Registrar');
});

test('REQ-06-03 the registrar sees the full ledger with actors', function () {
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $this->request))
        ->assertOk()
        ->assertSee('History')
        ->assertSee('Lina Librarian (Department Staff)')
        ->assertSee('Regina Registrar (Registrar)')
        ->assertSee('Signed by Library');
});

test('REQ-06-04 department staff see the request history', function () {
    $this->actingAs($this->librarian)->get(route('department.requests.show', $this->request))
        ->assertOk()
        ->assertSeeInOrder(['Signed by Library', 'On hold at Library']);
});
