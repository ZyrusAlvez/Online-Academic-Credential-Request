<?php

use App\Actions\Auth\ConfirmVerificationCode;
use App\Enums\AcademicStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestChannel;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Exceptions\InvalidTransition;
use App\Livewire\Cashier\Payments as CashierPayments;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Registrar\WalkIn;
use App\Livewire\Student\Dashboard;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Notifications\VerificationCode;
use App\Services\RequestWorkflow;
use Database\Seeders\DemoRequestSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
});

/** A walk-in client's details as the Registrar enters them. */
function walkInClient(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Pedro', 'middle_name' => 'Cruz', 'last_name' => 'Villanueva', 'sex' => 'male', 'maiden_name' => null, 'email' => 'Pedro.V@Example.com',
        'contact_number' => '09171234567', 'academic_status' => AcademicStatus::Graduate->value, 'college_id' => test()->org->ccs->id,
        'program' => 'BS Computer Science', 'student_number' => '18-1234-567', 'attended_from' => 2018, 'attended_to' => 2022,
    ], $overrides);
}

/** Record a walk-in request through the workflow. */
function recordWalkIn(?CredentialType $type = null, array $client = []): CredentialRequest
{
    return workflow()->submitWalkIn(test()->registrar, [
        'credential_type_id' => ($type ?? test()->org->tor)->id, 'purpose' => 'Employment', 'receiving_institution' => null,
    ], walkInClient($client));
}

/** The Walk-in Request page with every field filled in. */
function filledWalkInPage(): Testable
{
    $page = Livewire::actingAs(test()->registrar)->test(WalkIn::class)
        ->set('form.credential_type_id', test()->org->tor->id)
        ->set('form.purpose', 'Employment');

    foreach (walkInClient() as $field => $value) {
        $page->set("profile.{$field}", $value ?? '');
    }

    return $page;
}

// ── The page ────────────────────────────────────────────────────────────────────────────────

test('REQ-26-01 Walk-in Request is first in the registrar sidebar and only the registrar opens it', function () {
    $this->actingAs($this->registrar)->get(route('registrar.evaluation'))->assertOk()
        ->assertSeeInOrder(['Walk-in Request', 'Initial Evaluation', 'Payment Validation', 'Outgoing', 'Final Quality Check', 'Release', 'Records']);
    $this->get(route('registrar.walk-in'))->assertOk();
    $this->get('/dashboard')->assertRedirect(route('registrar.evaluation'));

    foreach (['cashier', 'admin', 'student', 'departmentStaff'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())->get(route('registrar.walk-in'))->assertForbidden();
    }
});

test('REQ-26-02 the form has sections 1 to 3 with an email, and no valid ID, representative or notice', function () {
    $html = Livewire::actingAs($this->registrar)->test(WalkIn::class)->html();

    expect($html)->toContain('1. What Do You Need?')->toContain('2. Personal Information')->toContain('3. Academic Information')
        ->toContain('data-walk-in-email')
        ->not->toContain('Valid ID')->not->toContain('Who Will Transact')->not->toContain('Important notice')
        ->not->toContain('data-privacy-consent');
});

test('REQ-26-02 REQ-26-03 every entry is checked before the summary, the email included', function () {
    Livewire::actingAs($this->registrar)->test(WalkIn::class)
        ->call('review')
        ->assertHasErrors(['form.credential_type_id', 'form.purpose', 'profile.first_name', 'profile.email', 'profile.contact_number', 'profile.college_id'])
        ->assertDispatched('modal-show', name: 'profile-errors')
        ->assertSet('reviewing', false);

    filledWalkInPage()->set('profile.email', 'not-an-email')->call('review')->assertHasErrors('profile.email');
});

test('REQ-26-03 the summary shows sections 1 to 3 and the evaluator\'s validation, required to submit', function () {
    $page = filledWalkInPage()->call('review')->assertHasNoErrors()->assertSet('reviewing', true);

    $page->assertSeeInOrder(['Summary', '1. What Do You Need?', '2. Personal Information', 'pedro.v@example.com', '3. Academic Information', 'Evaluator\'s Validation',
        'I have validated the eligibility of this request and the requester\'s personal and academic information.'])
        ->assertDontSee('Data Privacy')->assertDontSee('Valid ID');
    expect(Str::after($page->html(), 'data-walk-in-attestation'))->toMatch('/data-submit-request[^>]*disabled|disabled[^>]*data-submit-request/');

    $page->call('submit')->assertHasErrors('attested');
    expect(CredentialRequest::count())->toBe(0);
});

// ── Submitting ──────────────────────────────────────────────────────────────────────────────

test('REQ-26-04 a submitted walk-in request goes straight to Payment Validation, and the registrar lands there', function () {
    $page = filledWalkInPage()->call('review')->set('attested', true)->call('submit')->assertHasNoErrors();

    $request = CredentialRequest::sole();
    $page->assertRedirect(route('registrar.payments.show', $request));

    expect($request)
        ->channel->toBe(RequestChannel::WalkIn)
        ->status->toBe(RequestStatus::PaymentVerification)
        ->reference_no->toBe(sprintf('rc%s-%05d-1', now()->format('y'), $request->application_id))
        ->user_id->toBeNull()
        ->requester_name->toBe('Pedro Cruz Villanueva')
        ->requester_email->toBe('pedro.v@example.com')
        ->student_number->toBe('18-1234-567')
        ->recorded_by->toBe($this->registrar->id)
        ->evaluated_by->toBe($this->registrar->id)
        ->identity_document_id->toBeNull()
        ->identity_status->toBe(ReviewStatus::Verified)
        ->amount_due->toBe('530.00')
        ->and($request->events()->sole())->type->toBe(RequestEventType::WalkInRecorded)->actor_id->toBe($this->registrar->id)
        ->and($request->events()->sole()->summary())->toBe('Walk-in request recorded at the Office of the Registrar');

    // In the registrar's Payment Validation, not the cashier's.
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'payment'])->assertSee($request->reference_no);
    Livewire::actingAs(User::factory()->cashier()->create())->test(CashierPayments::class)->assertDontSee($request->reference_no);
});

test('REQ-26-04 a free credential is accepted for processing at once', function () {
    $request = recordWalkIn($this->org->free);

    expect($request->fresh())->status->toBe(RequestStatus::AcceptedForProcessing)->accepted_by->toBe($this->registrar->id)
        ->and($request->steps()->count())->toBeGreaterThan(0);
});

// ── Walk-in payment ─────────────────────────────────────────────────────────────────────────

test('REQ-26-05 the registrar validates a walk-in payment with its receipt number and amount', function () {
    $request = recordWalkIn();

    $page = Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()]);
    $page->assertSeeHtml('data-walk-in-payment')->assertSee('Official receipt no.')->assertSee('Amount paid')
        ->assertSee('Validate Payment')->assertSee('Decline')->assertDontSee('Return to Requester')->assertDontSee('Proof of Payment');

    $page->call('validatePayment')->assertHasErrors(['receiptNo', 'amountPaid']);
    $page->set('receiptNo', 'OR-104455')->set('amountPaid', '0')->call('validatePayment')->assertHasErrors(['amountPaid']);
    $page->set('amountPaid', '530')->call('validatePayment')->assertHasNoErrors();

    expect($request->fresh())->status->toBe(RequestStatus::AcceptedForProcessing)->due_at->not->toBeNull()
        ->and($request->paymentProofs()->sole())
        ->status->toBe(PaymentStatus::Verified)->official_receipt_no->toBe('OR-104455')->amount->toBe('530.00')
        ->path->toBeNull()->reviewed_by->toBe($this->registrar->id);
});

test('REQ-26-05 a walk-in request is declined with remarks; the cashier cannot validate it', function () {
    $request = recordWalkIn();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->call('decline')->assertHasErrors('remarks')
        ->set('remarks', 'Not eligible.')->call('decline')->assertHasNoErrors();
    expect($request->fresh()->status)->toBe(RequestStatus::Declined);

    $other = recordWalkIn();
    expect(User::factory()->cashier()->create()->can('validatePayment', $other))->toBeFalse()
        ->and(fn () => workflow()->verifyPaymentAndAccept($other, $this->registrar))->toThrow(InvalidTransition::class);
});

test('REQ-26-05 a walk-in payment is never returned: no button, and the workflow refuses it', function () {
    $request = recordWalkIn();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->assertSeeHtml('data-validate-payment-button')
        ->assertDontSeeHtml('data-return-button');

    expect(fn () => workflow()->rejectPayment($request->fresh(), $this->registrar, 'The receipt does not match.'))
        ->toThrow(InvalidTransition::class, 'A walk-in payment cannot be returned. Validate it or decline the request.');
    expect($request->fresh()->status)->toBe(RequestStatus::PaymentVerification);
});

test('REQ-26-06 a walk-in payment shows its receipt number and no file wherever payments are listed', function () {
    $request = recordWalkIn();
    workflow()->verifyWalkInPayment($request, $this->registrar, 'OR-104455', '530');
    $proof = $request->paymentProofs()->sole();

    $this->actingAs($this->registrar)->get(route('records.show', $request))->assertOk()
        ->assertSee('Official receipt no. OR-104455')->assertDontSee(route('payment-proofs.show', $proof));
    $this->get(route('payment-proofs.show', $proof))->assertNotFound();
});

// ── Linking ─────────────────────────────────────────────────────────────────────────────────

test('REQ-26-07 verifying an account\'s email by the code links its walk-in requests and fills an empty profile', function () {
    Notification::fake();
    $mine = recordWalkIn();
    $second = recordWalkIn($this->org->free);
    $someoneElse = recordWalkIn(client: ['email' => 'other@example.com']);

    $user = User::factory()->unverified()->create(['email' => 'pedro.v@example.com', 'contact_number' => null, 'college_id' => null, 'program' => null, 'academic_status' => null]);
    $user->sendEmailVerificationNotification();
    app(ConfirmVerificationCode::class)->handle($user, Notification::sent($user, VerificationCode::class)->last()->code);

    expect($mine->fresh()->user_id)->toBe($user->id)
        ->and($second->fresh()->user_id)->toBe($user->id)
        ->and($someoneElse->fresh()->user_id)->toBeNull()
        ->and($mine->events()->where('type', RequestEventType::LinkedToAccount)->sole())->actor_id->toBe($user->id)
        ->and($user->fresh())->contact_number->toBe('09171234567')->college_id->toBe($this->org->ccs->id)->program->toBe('BS Computer Science');
});

test('REQ-26-07 signing in with Google links the walk-in requests made with that email', function () {
    config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
    $request = recordWalkIn();

    $google = (new GoogleUser)->setRaw(['sub' => 'g-1', 'email' => 'pedro.v@example.com', 'email_verified' => true, 'given_name' => 'Pedro', 'family_name' => 'Villanueva'])
        ->map(['id' => 'g-1', 'email' => 'pedro.v@example.com', 'name' => 'Pedro Villanueva']);
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($google);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect();

    expect($request->fresh()->user_id)->toBe(User::where('email', 'pedro.v@example.com')->sole()->id);
});

test('REQ-26-08 a walk-in for an existing requester account is linked at once, never to staff or another account', function () {
    $owner = requester($this->org->ccs);
    $request = recordWalkIn(client: ['email' => strtoupper($owner->email)]);
    expect($request->user_id)->toBe($owner->id);

    // A staff account's email is never a requester's.
    $forStaff = recordWalkIn(client: ['email' => $this->registrar->email]);
    expect($forStaff->user_id)->toBeNull();

    // Already linked: another account with the email changed to it does not take it.
    $other = User::factory()->withProfile($this->org->ccs)->create();
    workflow()->linkToAccount($request, $other);
    expect($request->fresh()->user_id)->toBe($owner->id);
});

test('REQ-26-09 a linked walk-in request is the owner\'s; before linking nobody is notified', function () {
    Notification::fake();
    $unlinked = recordWalkIn();
    workflow()->verifyWalkInPayment($unlinked, $this->registrar, 'OR-1', '530');
    // Only staff hear about it (the first office); no requester.
    $notified = array_keys(Notification::sentNotifications()[User::class] ?? []);
    expect(User::whereIn('id', $notified)->where('role', Role::Student)->exists())->toBeFalse();

    $owner = requester($this->org->ccs);
    $linked = recordWalkIn(client: ['email' => $owner->email]);
    workflow()->verifyWalkInPayment($linked, $this->registrar, 'OR-2', '530');
    Notification::assertSentTo($owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::Accepted);

    Livewire::actingAs($owner)->test(Dashboard::class)->assertSee($linked->reference_no)->assertDontSee($unlinked->reference_no);
    $this->actingAs($owner)->get(route('student.requests.show', $linked))->assertOk();
    $this->actingAs($owner)->get(route('student.requests.show', $unlinked))->assertNotFound();
});

// ── Showing walk-in requests ────────────────────────────────────────────────────────────────

test('REQ-26-10 queues, Records and the record mark a walk-in request and show its own name and email', function () {
    $request = recordWalkIn();

    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'payment'])->assertSeeHtml('data-walk-in-badge');
    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)->assertSeeHtml('data-walk-in-badge')->assertSee('Pedro Cruz Villanueva');
    $this->actingAs($this->registrar)->get(route('records.show', $request))->assertOk()
        ->assertSee('Walk-in')->assertSee('pedro.v@example.com');

    $online = submitRequest(requester($this->org->ccs), $this->org->tor);
    $this->get(route('records.show', $online))->assertDontSeeHtml('data-walk-in-badge');
});

test('REQ-26-11 the demo data has a walk-in request with no account and one linked to a demo requester', function () {
    $this->seed(DemoRequestSeeder::class);

    $walkIns = CredentialRequest::where('channel', RequestChannel::WalkIn)->get();
    expect($walkIns->whereNull('user_id'))->toHaveCount(1)
        ->and($walkIns->whereNotNull('user_id'))->toHaveCount(1)
        ->and(User::find($walkIns->whereNotNull('user_id')->first()->user_id)->email)->toBe('ana@example.com');
});
