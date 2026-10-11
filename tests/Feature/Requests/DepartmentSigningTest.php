<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Department\Dashboard;
use App\Livewire\Department\Requests\Show;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->workflow = app(RequestWorkflow::class);
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
    $this->librarian = User::factory()->departmentStaff($this->org->library)->create();
    $this->cashier = User::factory()->departmentStaff($this->org->cashier)->create();
    $this->ccsDean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->cbaDean = User::factory()->departmentStaff($this->org->cba)->create();
});

/** A routed TOR request (Library → Cashier → CCS → Registrar) skipping the payment UI. */
function routedTor(?User $student = null): CredentialRequest
{
    $request = submitRequest($student ?? test()->student, test()->org->free, test()->org->ccs);
    // Route through the TOR workflow by switching the type before approval.
    $request->forceFill(['credential_type_id' => test()->org->tor->id])->save();
    test()->workflow->approve($request, test()->registrar);

    return $request->fresh();
}

function currentStepOf(CredentialRequest $request)
{
    return $request->currentStep()->with('department', 'credentialRequest')->firstOrFail();
}

test('REQ-05-01 staff see current steps of their department, split by status, oldest first', function () {
    $first = routedTor();
    $second = routedTor(User::factory()->student()->create());
    $held = routedTor(User::factory()->student()->create());
    $this->workflow->hold(currentStepOf($held), $this->librarian, 'Unreturned book');

    Livewire::actingAs($this->librarian)->test(Dashboard::class)
        ->assertSeeInOrder([$first->reference_no, $second->reference_no])
        ->assertDontSee($held->reference_no)
        ->set('tab', 'on_hold')
        ->assertSee($held->reference_no)
        ->assertSee('Unreturned book');

    Livewire::actingAs($this->cashier)->test(Dashboard::class)->assertDontSee($first->reference_no);
});

test('REQ-05-02 staff see their department\'s signing history', function () {
    $request = routedTor();
    $this->workflow->sign(currentStepOf($request), $this->librarian);

    Livewire::actingAs($this->librarian)->test(Dashboard::class)
        ->assertDontSee($request->reference_no)
        ->set('tab', 'history')
        ->assertSee($request->reference_no)
        ->assertSee('Signed');
});

test('REQ-05-03 staff can open requests routed through them but not others', function () {
    $request = routedTor();

    $this->actingAs($this->librarian)->get(route('department.requests.show', $request))->assertOk()->assertSee($request->reference_no);
    $this->actingAs($this->cbaDean)->get(route('department.requests.show', $request))->assertNotFound();

    $unrouted = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    $this->actingAs($this->librarian)->get(route('department.requests.show', $unrouted))->assertNotFound();
});

test('REQ-05-04 signing marks the step and hands the request to the next office', function () {
    $request = routedTor();

    Livewire::actingAs($this->librarian)->test(Show::class, ['credentialRequest' => $request])
        ->set('signRemarks', 'No accountability')
        ->call('signStep')
        ->assertDispatched('notify');

    $steps = $request->steps()->get();
    expect($steps[0])
        ->status->toBe(StepStatus::Signed)
        ->acted_by->toBe($this->librarian->id)
        ->remarks->toBe('No accountability')
        ->acted_at->not->toBeNull()
        ->and($steps[1]->status)->toBe(StepStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::InProgress);
});

test('REQ-05-05 signing the last step makes the request ready for release', function () {
    $request = routedTor();
    $this->workflow->sign(currentStepOf($request), $this->librarian);
    $this->workflow->sign(currentStepOf($request), $this->cashier);
    $this->workflow->sign(currentStepOf($request), $this->ccsDean);
    $this->workflow->sign(currentStepOf($request), $this->registrar);

    expect($request->fresh())
        ->status->toBe(RequestStatus::ReadyForRelease)
        ->ready_at->not->toBeNull()
        ->and($request->events()->get()->last()->type)->toBe(RequestEventType::ReadyForRelease);
});

test('REQ-05-06 hold requires remarks; staff can lift it or sign directly', function () {
    $request = routedTor();
    $page = Livewire::actingAs($this->librarian)->test(Show::class, ['credentialRequest' => $request]);

    $page->call('holdStep')->assertHasErrors('holdRemarks');
    $page->set('holdRemarks', 'Unpaid fine of ₱50')->call('holdStep')->assertHasNoErrors();
    expect(currentStepOf($request))->status->toBe(StepStatus::OnHold)->remarks->toBe('Unpaid fine of ₱50');

    $page->call('resumeStep');
    expect(currentStepOf($request))->status->toBe(StepStatus::Pending)->acted_by->toBeNull();

    $page->set('holdRemarks', 'Again')->call('holdStep')->call('signStep');
    expect($request->steps()->first()->status)->toBe(StepStatus::Signed);
});

test('REQ-05-07 staff cannot act on another department\'s or a non-current step', function () {
    $request = routedTor();

    // The Cashier's step is still waiting: it may look, but not act.
    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request])
        ->call('signStep')
        ->assertForbidden();

    $this->workflow->sign(currentStepOf($request), $this->librarian);

    // The librarian can still view it, but their step is no longer current.
    Livewire::actingAs($this->librarian)->test(Show::class, ['credentialRequest' => $request])
        ->call('signStep')
        ->assertForbidden();

    expect($request->steps()->get()[1]->status)->toBe(StepStatus::Pending);
});

test('REQ-05-07 the CBA dean never sees a CCS student\'s request', function () {
    $request = routedTor();
    $this->workflow->sign(currentStepOf($request), $this->librarian);
    $this->workflow->sign(currentStepOf($request), $this->cashier);

    Livewire::actingAs($this->cbaDean)->test(Dashboard::class)->assertDontSee($request->reference_no);
    Livewire::actingAs($this->ccsDean)->test(Dashboard::class)->assertSee($request->reference_no);
});

test('REQ-05-08 registrar accounts act on Office of the Registrar steps only', function () {
    $request = routedTor();

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $request])
        ->call('signStep')
        ->assertForbidden();   // current step is the Library's

    foreach ([$this->librarian, $this->cashier, $this->ccsDean] as $staff) {
        $this->workflow->sign(currentStepOf($request), $staff);
    }

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee($request->reference_no);

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $request->fresh()])
        ->call('signStep')
        ->assertDispatched('notify');

    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);
});

test('REQ-05-09 the registrar can skip the current step with a reason', function () {
    $request = routedTor();
    $page = Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $request]);

    $page->call('skipStep')->assertHasErrors('skipReason');
    $page->set('skipReason', 'Library clearance not required for alumni')->call('skipStep')->assertHasNoErrors();

    $steps = $request->steps()->get();
    expect($steps[0])->status->toBe(StepStatus::Skipped)->remarks->toBe('Library clearance not required for alumni')
        ->and($steps[1]->status)->toBe(StepStatus::Pending);

    Livewire::actingAs($this->librarian)->test(Show::class, ['credentialRequest' => $request])
        ->call('signStep')
        ->assertForbidden();
});

test('REQ-05-10 the registrar can release a ready request', function () {
    $request = routedTor();
    foreach ([$this->librarian, $this->cashier, $this->ccsDean, $this->registrar] as $actor) {
        $this->workflow->sign(currentStepOf($request), $actor);
    }

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $request->fresh()])
        ->call('release')
        ->assertDispatched('notify');

    expect($request->fresh())->status->toBe(RequestStatus::Released)->released_at->not->toBeNull();

    expect(fn () => $this->workflow->release($request, $this->registrar))->toThrow(InvalidTransition::class);
});

test('REQ-05-11 the registrar can cancel an in-progress request, removing it from queues', function () {
    $request = routedTor();

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $request])
        ->set('cancelReason', 'Duplicate request')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled);
    Livewire::actingAs($this->librarian)->test(Dashboard::class)->assertDontSee($request->reference_no);
});

test('REQ-05-12 step actions are recorded with department, actor and remarks', function () {
    $request = routedTor();
    $this->workflow->hold(currentStepOf($request), $this->librarian, 'Book overdue');
    $this->workflow->resume(currentStepOf($request), $this->librarian);
    $this->workflow->sign(currentStepOf($request), $this->librarian, 'Cleared');

    $events = $request->events()->get()->slice(-3)->values();
    expect($events->pluck('type')->all())->toBe([RequestEventType::StepHeld, RequestEventType::StepResumed, RequestEventType::StepSigned])
        ->and($events->pluck('department_id')->unique()->all())->toBe([$this->org->library->id])
        ->and($events->pluck('actor_id')->unique()->all())->toBe([$this->librarian->id])
        ->and($events->pluck('remarks')->all())->toBe(['Book overdue', null, 'Cleared']);
});

test('REQ-05-13 a second action on an already-completed step is refused', function () {
    $request = routedTor();
    $staleCopy = currentStepOf($request);
    $secondLibrarian = User::factory()->departmentStaff($this->org->library)->create();

    $this->workflow->sign($staleCopy, $this->librarian);

    expect(fn () => $this->workflow->sign($staleCopy, $secondLibrarian))
        ->toThrow(InvalidTransition::class, 'This step has already been completed.');

    expect($request->steps()->first()->acted_by)->toBe($this->librarian->id)
        ->and($request->events()->where('type', RequestEventType::StepSigned)->count())->toBe(1);
});
