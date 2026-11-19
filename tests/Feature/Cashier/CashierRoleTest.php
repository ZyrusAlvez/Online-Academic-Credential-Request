<?php

use App\Enums\PaymentStatus;
use App\Enums\RegistrarQueue;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index as Accounts;
use App\Livewire\Cashier\Payments;
use App\Livewire\Cashier\Requests\Show;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
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

// ── The role ────────────────────────────────────────────────────────────────────────────────

test('REQ-25-01 the cashier lands on Payment Validation', function () {
    $this->actingAs($this->cashier)->get('/dashboard')->assertRedirect(route('cashier.payments'));
    $this->get('/cashier')->assertRedirect('/cashier/payments');
});

test('REQ-25-02 the cashier sidebar has Payment Validation only, and Settings has email notifications', function () {
    $this->actingAs($this->cashier)->get(route('cashier.payments'))->assertOk()
        ->assertSee('Payment Validation')
        ->assertDontSee(route('registrar.evaluation'))
        ->assertDontSee(route('records'))
        ->assertDontSee(route('admin.dashboard'));

    $this->get(route('settings.notifications'))->assertOk();
    $this->get(route('settings.password'))->assertOk();
});

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

test('REQ-25-05 after a decision the list goes back as the cashier left it', function () {
    $request = paidOnline($this);
    $list = route('cashier.payments', ['search' => $request->reference_no]);

    Livewire::withHeaders(['Referer' => $list])->actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request])
        ->assertSeeHtml('href="'.e($list).'"')
        ->call('approve')
        ->assertRedirect($list);
});

test('REQ-25-05 a request with no payment to validate goes back to the list', function () {
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $request])
        ->assertRedirect(route('cashier.payments'));

    expect($this->cashier->can('validatePayment', paidWalkIn($this)))->toBeFalse();
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

// ── Accounts and demo ───────────────────────────────────────────────────────────────────────

test('REQ-25-09 the admin creates a cashier account without a department and can change a role to Cashier', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Accounts::class)
        ->call('create')
        ->set('form.first_name', 'New')->set('form.last_name', 'Cashier')
        ->set('form.email', 'new.cashier@uphsl.edu.ph')->set('form.password', 'Secret123!')
        ->set('form.role', 'cashier')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'new.cashier@uphsl.edu.ph')->sole())->role->toBe(Role::Cashier)->department_id->toBeNull();

    $staff = User::factory()->departmentStaff($this->org->ccs)->create();
    Livewire::actingAs($admin)->test(Accounts::class)
        ->call('editRole', $staff->id)
        ->set('roleForm.role', 'cashier')
        ->call('changeRole')
        ->assertHasNoErrors();

    expect($staff->fresh())->role->toBe(Role::Cashier)->department_id->toBeNull();
});

test('REQ-25-10 the demo data includes the cashier@example.com cashier', function () {
    DemoSeeder::cashierAccount();
    DemoSeeder::cashierAccount();   // the demo reset runs it again

    $cashier = User::where('email', 'cashier@example.com')->sole();
    expect($cashier)->role->toBe(Role::Cashier)->is_active->toBeTrue()
        ->and($cashier->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('@Test123', $cashier->password))->toBeTrue();
});
