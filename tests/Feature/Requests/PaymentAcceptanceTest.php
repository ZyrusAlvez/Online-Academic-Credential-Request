<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitStatus;
use App\Livewire\Cashier\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->cashier = User::factory()->cashier()->create();
    $this->request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
});

afterEach(fn () => Carbon::setTestNow());

test('REQ-04-12 the owner sees the amount due and uploads a proof with the amount and date, no reference number', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Amount due: ₱530.00')
        ->assertSee(config('uphsl.payment_url'))
        ->assertDontSee('Payment reference no.')
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->set('amountPaid', '530')
        ->set('paidOn', now()->format('Y-m-d'))
        ->call('uploadReceipt')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: 'Proof of payment uploaded. The Cashier will verify it against the payment record.');

    $proof = $this->request->paymentProofs()->sole();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($proof->path);
    expect($this->request->fresh()->status)->toBe(RequestStatus::PaymentVerification)
        ->and($proof)->reference_no->toBeNull()->amount->toBe('530.00')->paid_on->toDateString()->toBe(now()->toDateString());
});

test('REQ-04-12 the proof must be an image or PDF of at most 5 MB with its details', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->set('receipt', UploadedFile::fake()->create('receipt.exe', 100))
        ->call('uploadReceipt')
        ->assertHasErrors(['receipt' => 'mimes', 'amountPaid' => 'required', 'paidOn' => 'required']);

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

test('REQ-04-13 the cashier rejects a proof with remarks', function () {
    uploadPayment($this->request);

    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $this->request->fresh()])
        ->call('returnRequest')->assertHasErrors('remarks')
        ->set('remarks', 'No matching transaction')->call('returnRequest')->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($this->request->paymentProofs()->sole())->status->toBe(PaymentStatus::Rejected)->remarks->toBe('No matching transaction');
});

test('REQ-04-13 a proof alone is not paid; the cashier verifies it, with no receipt number', function () {
    uploadPayment($this->request);
    expect($this->request->fresh()->accepted_at)->toBeNull();

    Livewire::actingAs($this->cashier)->test(Show::class, ['credentialRequest' => $this->request->fresh()])
        ->call('approve')->assertHasNoErrors();

    expect($this->request->paymentProofs()->sole())
        ->status->toBe(PaymentStatus::Verified)
        ->official_receipt_no->toBeNull()
        ->reviewed_by->toBe($this->cashier->id)
        ->and($this->request->fresh()->accepted_at)->not->toBeNull()
        ->and($this->request->events()->where('type', RequestEventType::PaymentVerified)->value('remarks'))->toBeNull();
});

/** The requester's page with a new proof filled in, ready to upload. */
function newProof(): Testable
{
    return Livewire::actingAs(test()->owner)->test(RequesterShow::class, ['credentialRequest' => test()->request->fresh()])
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->set('amountPaid', '530')
        ->set('paidOn', now()->format('Y-m-d'));
}

test('REQ-04-20 after a returned proof the requester can send a note with the new one, and only then', function () {
    // No return yet: no note field.
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertDontSeeHtml('data-payment-note')->assertDontSee('Note to the Office of the Registrar');

    uploadPayment($this->request);
    workflow()->rejectPayment($this->request, $this->registrar, 'The reference does not match our record.');

    newProof()
        ->assertSee('The reference does not match our record.')
        ->assertSeeHtml('data-payment-note')->assertSee('Note to the Office of the Registrar')
        ->set('paymentNote', str_repeat('x', 501))->call('uploadReceipt')->assertHasErrors(['paymentNote' => 'max'])
        ->set('paymentNote', 'I paid at the bank; the reference is on the stamped slip.')->call('uploadReceipt')->assertHasNoErrors()
        ->assertSet('paymentNote', '');

    expect($this->request->fresh()->status)->toBe(RequestStatus::PaymentVerification)
        ->and($this->request->events()->get()->last())
        ->type->toBe(RequestEventType::PaymentSubmitted)
        ->remarks->toBe('I paid at the bank; the reference is on the stamped slip.');

    // Without a note, the upload records no remarks.
    workflow()->rejectPayment($this->request, $this->registrar, 'Still unreadable.');
    newProof()->call('uploadReceipt')->assertHasNoErrors();
    expect($this->request->events()->get()->last()->remarks)->toBeNull();
});

test('REQ-04-14 acceptance starts the clock: due date, route and first hand-off', function () {
    uploadPayment($this->request);
    Carbon::setTestNow('2026-10-09 10:00:00');   // a Friday
    workflow()->verifyPaymentAndAccept($this->request, $this->registrar);

    $request = $this->request->fresh();
    $steps = $request->steps()->with('department')->get();

    expect($request->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($request->accepted_by)->toBe($this->registrar->id)
        ->and($request->accepted_at->toDateTimeString())->toBe('2026-10-09 10:00:00')
        ->and($request->due_at->toDateTimeString())->toBe('2026-10-17 23:59:59')   // 7 working days, Mon–Sat
        ->and($request->effective_due_at->toDateTimeString())->toBe('2026-10-17 23:59:59')
        ->and($steps->map(fn ($s) => $s->department->code)->all())->toBe(['REG', 'CCS', 'VPF', 'CHAN', 'REG'])
        ->and($steps->last()->task)->toBe(RouteTask::FinalCheck)
        ->and($steps->first()->status)->toBe(StepStatus::Current)
        ->and(openVisit($request)->status)->toBe(VisitStatus::Received)   // at the Registrar (REQ-17-05)
        ->and($request->events()->pluck('type')->all())->toContain(RequestEventType::PaymentVerified, RequestEventType::Accepted, RequestEventType::DocumentForwarded);
});
