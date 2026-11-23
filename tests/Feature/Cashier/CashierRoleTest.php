<?php

use App\Enums\PaymentStatus;
use App\Enums\RegistrarQueue;
use App\Enums\RequestStatus;
use App\Livewire\Cashier\Payments;
use App\Livewire\Cashier\Requests\Show;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->cashier = User::factory()->cashier()->create();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

/** An online request whose proof of payment awaits verification. */
function paidOnline(object $test): CredentialRequest
{
    $request = evaluate(submitRequest($test->owner, $test->org->tor), $test->registrar);
    uploadPayment($request);

    return $request->fresh();
}

/** The same, made walk-in (spec 26 records them; here the channel is set directly). */
function paidWalkIn(object $test): CredentialRequest
{
    return walkIn(paidOnline($test));
}

test('REQ-25-03 the cashier cannot open registrar, department, records or admin pages', function () {
    $request = paidOnline($this);

    foreach (['registrar.evaluation', 'registrar.payments', 'records', 'admin.dashboard', 'admin.accounts', 'department.incoming'] as $page) {
        $this->actingAs($this->cashier)->get(route($page))->assertForbidden();
    }

    $this->actingAs($this->cashier)->get(route('registrar.requests.show', $request))->assertForbidden();
    Livewire::actingAs($this->cashier)->test(Queue::class, ['queue' => 'payment'])->assertForbidden();
});

test('REQ-25-03 only the cashier opens the cashier pages', function (string $state) {
    $request = paidOnline($this);
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('cashier.payments'))->assertForbidden();
    $this->actingAs($user)->get(route('cashier.payments.show', $request))->assertForbidden();
})->with(['registrar', 'admin', 'departmentStaff', 'student']);

// ── Payment Validation ──────────────────────────────────────────────────────────────────────

test('REQ-25-04 the cashier lists online proofs to verify, and the online requests awaiting payment apart', function () {
    $online = paidOnline($this);
    $walkIn = paidWalkIn($this);
    $awaiting = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    Livewire::actingAs($this->cashier)->test(Payments::class)
        ->assertSee($online->reference_no)
        ->assertDontSee($walkIn->reference_no)
        ->assertDontSee($awaiting->reference_no)
        ->assertSeeHtml('href="'.route('cashier.payments.show', $online).'"')
        ->assertSee('Waiting for the requester (1)')
        ->set('waiting', true)
        ->assertSee($awaiting->reference_no)
        ->assertDontSee($online->reference_no)
        ->set('waiting', false)
        ->set('search', 'no-such-request')
        ->assertDontSee($online->reference_no);
});

test('REQ-25-05 the cashier sees the proof and approves: the request is accepted', function () {
    $request = paidOnline($this);

    $this->actingAs($this->cashier)->get(route('cashier.payments.show', $request))->assertOk()
        ->assertSee('Proof of Payment')->assertSee('Payment Details')
        ->assertSee('Decline')->assertSee('Return to Requester')->assertSee('Approve');

    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request])
        ->call('approve')
        ->assertRedirect(route('cashier.payments'));

    expect($request->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($request->paymentProofs()->sole())->status->toBe(PaymentStatus::Verified)->reviewed_by->toBe($this->cashier->id);
});

test('REQ-25-05 the cashier returns a proof with remarks, and declines with remarks', function () {
    $request = paidOnline($this);

    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request])
        ->call('returnRequest')->assertHasErrors('remarks')
        ->set('remarks', 'The receipt is unreadable.')
        ->call('returnRequest')->assertHasNoErrors()->assertRedirect(route('cashier.payments'));

    expect($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);

    uploadPayment($request);
    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->call('decline')->assertHasErrors('remarks')
        ->set('remarks', 'Not eligible.')
        ->call('decline')->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Declined);
});

test('REQ-25-06 the cashier opens the proof of payment of an online request', function () {
    $request = paidOnline($this);

    $this->actingAs($this->cashier)->get(route('payment-proofs.show', $request->paymentProofs()->sole()))->assertOk();
});

test('REQ-25-07 the registrar lists walk-in payments only, with no waiting tab, and cannot validate an online payment', function () {
    $online = paidOnline($this);
    $walkIn = paidWalkIn($this);

    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => RegistrarQueue::Payment->value])
        ->assertSee($walkIn->reference_no)
        ->assertDontSee($online->reference_no)
        ->assertDontSee('Waiting for the requester');

    // The online request's registrar page is its record.
    $this->actingAs($this->registrar)->get(route('registrar.requests.show', $online))->assertRedirect(route('records.show', $online));
    expect($this->registrar->can('validatePayment', $online))->toBeFalse()
        ->and($this->registrar->can('validatePayment', $walkIn))->toBeTrue();

    Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $online])
        ->call('approve')->assertForbidden();
    expect($online->fresh()->status)->toBe(RequestStatus::PaymentVerification);
});

// ── Notifications ───────────────────────────────────────────────────────────────────────────

test('REQ-25-08 an uploaded proof notifies the cashiers, not the registrars, with a link to the cashier page', function () {
    Notification::fake();
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    uploadPayment($request);

    Notification::assertSentTo($this->cashier, RequestActivity::class, function (RequestActivity $notification) use ($request) {
        return str_contains(json_encode($notification->toArray($this->cashier)), str_replace('/', '\/', route('cashier.payments.show', $request)));
    });
    Notification::assertNotSentTo($this->registrar, RequestActivity::class, fn (RequestActivity $notification) => str_contains(json_encode($notification->toArray($this->registrar)), 'Payment to verify'));
});
