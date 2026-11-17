<?php

use App\Enums\CheckStage;
use App\Enums\RegistrarQueue;
use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Requests\Show;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Support\RegistrarLink;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

/** Whether the page marks the queue's sidebar link as the current one. */
function queueIsCurrent(string $html, RegistrarQueue $queue): bool
{
    return preg_match('#<a(?=[^>]*href="'.preg_quote(route($queue->routeName()), '#').'")(?=[^>]*\sdata-current(?=[\s>="]))[^>]*>#', $html) === 1;
}

/** An accepted TOR taken through every office and received for the final quality check. */
function throughEveryOffice(CredentialRequest $request): CredentialRequest
{
    $registrar = test()->registrar;
    acceptRequest($request, $registrar);
    passOffice($request, $registrar);
    passOffice($request, User::factory()->departmentStaff(test()->org->ccs)->create());
    passOffice($request, User::factory()->departmentStaff(test()->org->vpf)->create());
    passOffice($request, User::factory()->departmentStaff(test()->org->chancellor)->create());
    workflow()->acceptReceipt(openVisit($request), $registrar);

    return $request->fresh();
}

/** A request in each queue. */
function oneInEachQueue(): array
{
    $registrar = test()->registrar;
    $evaluation = submitRequest(test()->owner, test()->org->tor);
    $payment = walkIn(evaluate(submitRequest(test()->owner, test()->org->tor), $registrar));
    uploadPayment($payment);
    $outgoing = acceptRequest(submitRequest(test()->owner, test()->org->tor), $registrar);
    $inProgress = acceptRequest(submitRequest(test()->owner, test()->org->tor), $registrar);
    passOffice($inProgress, $registrar);   // forwarded to the Dean, not yet received
    $finalCheck = throughEveryOffice(submitRequest(test()->owner, test()->org->tor));
    $release = throughEveryOffice(submitRequest(test()->owner, test()->org->tor));
    workflow()->approveFinalCheck($release, $registrar);

    return [
        'evaluation' => [RegistrarQueue::Evaluation, $evaluation->fresh()],
        'payment' => [RegistrarQueue::Payment, $payment->fresh()],
        'in progress' => [RegistrarQueue::InProgress, $inProgress->fresh()],
        'outgoing' => [RegistrarQueue::Outgoing, $outgoing->fresh()],
        'final check' => [RegistrarQueue::FinalCheck, $finalCheck->fresh()],
        'release' => [RegistrarQueue::Release, $release->fresh()],
    ];
}

test('REQ-16-26 each request opens under its queue, with that queue current in the sidebar and as the breadcrumb; In Progress has no page', function () {
    foreach (oneInEachQueue() as [$queue, $request]) {
        expect(RegistrarQueue::forRequest($request))->toBe($queue);

        if (! $queue->hasWorkingPage()) {
            $this->actingAs($this->registrar)->get(route($queue->routeName()).'/'.$request->reference_no)->assertNotFound();
            expect(RegistrarLink::to($request))->toBe(route($queue->routeName(), ['search' => $request->reference_no]));

            continue;
        }

        $url = route($queue->showRouteName(), $request);
        expect($url)->toEndWith('/registrar/'.str_replace(['registrar.', '.'], ['', '/'], $queue->routeName()).'/'.$request->reference_no);

        $html = $this->actingAs($this->registrar)->get($url)->assertOk()->getContent();
        expect(queueIsCurrent($html, $queue))->toBeTrue("{$queue->label()} is not current")
            ->and(Str::between($html, 'data-flux-breadcrumbs', $request->reference_no))->toContain($queue->label());

        foreach (RegistrarQueue::cases() as $other) {
            if ($other !== $queue) {
                expect(queueIsCurrent($html, $other))->toBeFalse("{$other->label()} is current on {$queue->label()}");
            }
        }
    }
});

test('REQ-16-26 the queues link their rows to the working page under them', function () {
    $payment = walkIn(evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar));
    uploadPayment($payment);
    $submitted = submitRequest($this->owner, $this->org->tor);

    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => RegistrarQueue::Payment->value])
        ->assertSeeHtml('href="'.route('registrar.payments.show', $payment).'"');
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => RegistrarQueue::Evaluation->value])
        ->assertSeeHtml('href="'.route('registrar.evaluation.show', $submitted).'"');
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

test('REQ-16-26 after a decision moves the request, the page follows it to its new queue', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    checkAll($request, $this->registrar, CheckStage::Evaluation);

    // An online request's payment is the cashier's: the registrar's page for it is its record (REQ-25-07).
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh(), 'queue' => RegistrarQueue::Evaluation->value])
        ->call('approve')
        ->assertHasNoErrors()
        ->assertRedirect(route('records.show', $request));

    // Within the same queue the page stays.
    $other = submitRequest($this->owner, $this->org->tor);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $other, 'queue' => RegistrarQueue::Evaluation->value])
        ->call('startEvaluation')
        ->assertNoRedirect();
});

test('REQ-16-26 a payment approved at Payment Validation moves to Outgoing, where the Registrar prepares and forwards it', function () {
    $request = walkIn(evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar));
    uploadPayment($request);

    // A walk-in payment, validated with its receipt (REQ-26-05).
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh(), 'queue' => RegistrarQueue::Payment->value])
        ->set('receiptNo', 'OR-1001')->set('amountPaid', '530')
        ->call('validatePayment')
        ->assertRedirect(route('registrar.outgoing', ['search' => $request->reference_no]));

    expect(session('notify'))->toBe('Payment validated and request accepted. The processing period has started.');
});

test('REQ-16-26 the former address in notifications always lands where the request is now', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    $link = route('registrar.requests.show', $request);
    expect($link)->toEndWith('/registrar/requests/'.$request->reference_no);

    $this->actingAs($this->registrar)->get($link)->assertRedirect(route('registrar.evaluation.show', $request));

    evaluate($request, $this->registrar);
    // Awaiting an online payment: the cashier's, so the registrar sees the record (REQ-25-07).
    $this->actingAs($this->registrar)->get($link)->assertRedirect(route('records.show', $request));
    walkIn($request);
    $this->actingAs($this->registrar)->get($link)->assertRedirect(route('registrar.payments.show', $request));

    // Accepted, at the Registrar: the Outgoing list, searched by its tracking number (REQ-32-02).
    $accepted = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $list = route('registrar.outgoing', ['search' => $accepted->reference_no]);
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $accepted))->assertRedirect($list);
    $this->actingAs($this->registrar)->get($list)->assertOk()->assertSee($accepted->reference_no);

    // Forwarded to the Dean: the In Progress list.
    passOffice($accepted, $this->registrar);
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $accepted))
        ->assertRedirect(route('registrar.in-progress', ['search' => $accepted->reference_no]));
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
        $request->channel = RequestChannel::WalkIn;
        expect(RegistrarQueue::forRequest($request))->toBe($queue, $status);

        // An online payment is the cashier's, in no registrar queue (REQ-25-07).
        $request->channel = RequestChannel::Online;
        expect(RegistrarQueue::forRequest($request))->toBe($queue === RegistrarQueue::Payment ? null : $queue, $status);
    }
});
