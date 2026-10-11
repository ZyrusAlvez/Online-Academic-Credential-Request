<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Student\Requests\Show;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\RequestWorkflow;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
    $this->workflow = app(RequestWorkflow::class);
});

test('REQ-04-13 students can cancel before routing with an optional reason', function () {
    $request = submitRequest($this->student, $this->org->tor, $this->org->ccs);

    Livewire::actingAs($this->student)->test(Show::class, ['credentialRequest' => $request])
        ->set('cancelReason', 'No longer needed')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($request->fresh())
        ->status->toBe(RequestStatus::Cancelled)
        ->cancel_reason->toBe('No longer needed')
        ->cancelled_at->not->toBeNull();
});

test('REQ-04-13 students cannot cancel once the request is routed', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);

    Livewire::actingAs($this->student)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->call('cancel')
        ->assertForbidden();

    expect(fn () => $this->workflow->cancel($request, $this->student))->toThrow(InvalidTransition::class);
    expect($request->fresh()->status)->toBe(RequestStatus::InProgress);
});

test('REQ-04-14 every transition appends a ledger row with actor and statuses', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar, 'Looks good');

    $routed = $request->events()->get()->last();
    expect($routed)
        ->type->toBe(RequestEventType::Routed)
        ->actor_id->toBe($this->registrar->id)
        ->from_status->toBe(RequestStatus::RegistrarReview)
        ->to_status->toBe(RequestStatus::InProgress)
        ->remarks->toBe('Looks good');
});

test('REQ-04-14 ledger rows cannot be updated or deleted', function () {
    $event = submitRequest($this->student, $this->org->free, $this->org->ccs)->events()->sole();

    expect(fn () => $event->update(['remarks' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(RequestEvent::sole()->remarks)->toBeNull();
});

test('REQ-04-15 refused transitions change nothing', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->cancel($request, $this->student);

    expect(fn () => $this->workflow->approve($request, $this->registrar))->toThrow(InvalidTransition::class)
        ->and(fn () => $this->workflow->cancel($request, $this->registrar, 'again'))->toThrow(InvalidTransition::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($request->events()->count())->toBe(2);
});
