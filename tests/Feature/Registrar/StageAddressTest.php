<?php

use App\Enums\RegistrarQueue;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Livewire\Registrar\Queue;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| A request's working page lives under the queue it is in, and that queue is current in the
| sidebar (REQ-16-26).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-16-26 a page under the wrong queue, or at the former address, goes to the request\'s queue; a closed one to Records', function () {
    $payment = walkIn(evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar));
    uploadPayment($payment);

    $this->actingAs($this->registrar)->get(route('registrar.evaluation.show', $payment))
        ->assertRedirect(route('registrar.payments.show', $payment));
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $payment))
        ->assertRedirect(route('registrar.payments.show', $payment));

    $declined = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($declined, $this->registrar);
    workflow()->decline($declined, $this->registrar, 'Not eligible.');
    expect(RegistrarQueue::forRequest($declined->fresh()))->toBeNull();
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $declined))
        ->assertRedirect(route('records.show', $declined));
    $this->actingAs($this->registrar)->get(route('registrar.evaluation.show', $declined))
        ->assertRedirect(route('records.show', $declined));

    // Only registrars: the queue pages stay theirs (REQ-18-05).
    $this->actingAs(User::factory()->admin()->create())->get(route('registrar.payments.show', $payment))->assertForbidden();
    $this->actingAs($this->owner)->get(route('registrar.requests.show', $payment))->assertForbidden();
});

test('REQ-16-26 a request\'s queue follows its status', function () {
    $request = CredentialRequest::factory()->make();

    foreach ([
        'submitted' => RegistrarQueue::Evaluation, 'under_evaluation' => RegistrarQueue::Evaluation,
        'returned_for_clarification' => RegistrarQueue::Evaluation, 'pending_compliance' => RegistrarQueue::Evaluation,
        'awaiting_payment' => RegistrarQueue::Payment, 'payment_verification' => RegistrarQueue::Payment,
        'ready_for_release' => RegistrarQueue::Release,
        'released' => null, 'declined' => null, 'cancelled' => null,
    ] as $status => $queue) {
        $request->status = RequestStatus::from($status);
        $request->request_type = RequestType::WalkIn;
        expect(RegistrarQueue::forRequest($request))->toBe($queue, $status);

        // An online payment is the cashier's, in no registrar queue (REQ-25-07).
        $request->request_type = RequestType::Online;
        expect(RegistrarQueue::forRequest($request))->toBe($queue === RegistrarQueue::Payment ? null : $queue, $status);
    }
});
