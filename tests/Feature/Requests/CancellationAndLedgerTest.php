<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\RequestEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-04-16 the owner can cancel before acceptance, reason optional', function () {
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $request])->call('cancel');

    expect($request->fresh())
        ->status->toBe(RequestStatus::Cancelled)
        ->cancelled_at->not->toBeNull();
});

test('REQ-04-16 the owner cannot cancel an accepted request', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);

    expect($this->owner->can('cancel', $request))->toBeFalse()
        ->and(fn () => workflow()->cancel($request, $this->owner))->toThrow(InvalidTransition::class);
});

test('REQ-04-16 the registrar can cancel any open request with a reason, closing its open visit', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->call('cancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Duplicate request')->call('cancel');

    expect($request->fresh())
        ->status->toBe(RequestStatus::Cancelled)
        ->cancel_reason->toBe('Duplicate request')
        ->and($request->openVisit()->exists())->toBeFalse();
});

test('REQ-04-17 every action is in the append-only ledger with actor and statuses', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    $events = $request->events()->get();
    expect($events->pluck('type')->all())->toBe([RequestEventType::Submitted, RequestEventType::EvaluationStarted])
        ->and($events->last())
        ->actor_id->toBe($this->registrar->id)
        ->from_status->toBe(RequestStatus::Submitted)
        ->to_status->toBe(RequestStatus::UnderEvaluation);

    expect(fn () => $events->first()->update(['remarks' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $events->first()->delete())->toThrow(LogicException::class)
        ->and(RequestEvent::count())->toBe(2);
});

test('REQ-04-17 actions not allowed in the current status are refused', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    expect(fn () => workflow()->completeEvaluation($request, $this->registrar))->toThrow(InvalidTransition::class)
        ->and(fn () => workflow()->verifyPaymentAndAccept($request, $this->registrar, 'OR-1'))->toThrow(InvalidTransition::class)
        ->and(fn () => uploadPayment($request))->toThrow(InvalidTransition::class)
        ->and($request->fresh()->status)->toBe(RequestStatus::Submitted);
});
