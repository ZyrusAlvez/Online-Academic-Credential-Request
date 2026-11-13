<?php

use App\Enums\CheckStage;
use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitStatus;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
});

afterEach(fn () => Carbon::setTestNow());

test('REQ-04-12 the owner sees the amount due and uploads a proof with the transaction details', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Amount due: ₱530.00')
        ->assertSee(config('uphsl.payment_url'))
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->set('paymentReference', 'UPHSL-123')
        ->set('amountPaid', '530')
        ->set('paidOn', now()->format('Y-m-d'))
        ->call('uploadReceipt')
        ->assertHasNoErrors();

    $proof = $this->request->paymentProofs()->sole();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($proof->path);
    expect($this->request->fresh()->status)->toBe(RequestStatus::PaymentVerification)
        ->and($proof)->reference_no->toBe('UPHSL-123')->amount->toBe('530.00')->paid_on->toDateString()->toBe(now()->toDateString());
});

test('REQ-04-12 the proof must be an image or PDF of at most 5 MB with its details', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->set('receipt', UploadedFile::fake()->create('receipt.exe', 100))
        ->call('uploadReceipt')
        ->assertHasErrors(['receipt' => 'mimes', 'paymentReference' => 'required', 'amountPaid' => 'required', 'paidOn' => 'required']);

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

test('REQ-04-12 the proof field is the valid ID\'s picker, its file name a link to the viewer', function () {
    $html = Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])->html();
    $field = Str::after($html, 'data-receipt-picker');

    expect($html)->toContain('data-receipt-picker')
        ->and($field)->toMatch('/<input type="file" wire:model="receipt"[^>]*class="sr-only"/')
        ->toContain('Choose file')->toContain('No file chosen')
        ->toMatch('/<a href="#"[^>]*data-chosen-file[^>]*x-text="chosen\?\.name"\s*x-on:click\.prevent="\$dispatch\(\'open-file\', chosen\)"/')
        ->and(substr_count($html, 'wire:model="receipt"'))->toBe(1);
});

test('REQ-04-21 the uploaded proofs open from a Payment History button beside the notice, not inline', function () {
    $page = fn () => Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request->fresh()]);

    // Nothing uploaded yet: no button, no dialog.
    $page()->assertDontSeeHtml('data-payment-history-button')->assertDontSeeHtml('data-payment-history');

    // Being verified: the button sits in the notice; the proof is in the dialog only.
    uploadPayment($this->request);
    $proof = $this->request->paymentProofs()->sole();
    $page()->assertDontSee('Uploaded Proofs of Payment')
        ->assertSeeHtmlInOrder(['Your payment is being verified.', 'data-payment-history-button', 'Payment History'])
        ->assertSeeHtmlInOrder(['data-payment-history', 'Payment History', $proof->original_name, 'Ref REF-123', 'For verification']);

    // Returned: the button sits beside the reason; the dialog lists both proofs, newest first.
    workflow()->rejectPayment($this->request, $this->registrar, 'The reference does not match our record.');
    workflow()->submitPaymentProof($this->request, $this->owner, UploadedFile::fake()->image('second.jpg'), 'REF-456', '530', now()->format('Y-m-d'));
    workflow()->rejectPayment($this->request, $this->registrar, 'Still unreadable.');

    $html = Str::after($page()->assertDontSee('Uploaded Proofs of Payment')->html(), 'Your last proof of payment was not accepted');
    expect(Str::before($html, 'data-payment-history-button'))->toContain('Still unreadable.')
        ->and(Str::after($html, 'data-payment-history>'))->toMatch('/second\.jpg.*Remarks: Still unreadable\..*receipt\.jpg.*Remarks: The reference does not match our record\./s');
});

test('REQ-04-13 the registrar rejects a proof with remarks', function () {
    uploadPayment($this->request);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->call('returnRequest')->assertHasErrors('remarks')
        ->set('remarks', 'No matching transaction')->call('returnRequest')->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($this->request->paymentProofs()->sole())->status->toBe(PaymentStatus::Rejected)->remarks->toBe('No matching transaction');
});

test('REQ-04-13 a rejection that fails keeps the remarks typed', function () {
    uploadPayment($this->request);
    $page = Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request]);

    // Meanwhile another registrar verified the payment in another tab.
    checkAll($this->request, $this->registrar, CheckStage::Payment);
    workflow()->verifyPaymentAndAccept($this->request, User::factory()->registrar()->create(), 'OR-0001');

    $page->set('remarks', 'No matching transaction')->call('returnRequest')
        ->assertDispatched('notify')
        ->assertSet('remarks', 'No matching transaction');
});

test('REQ-04-13 verification needs the official receipt number; a proof alone is not paid', function () {
    uploadPayment($this->request);
    expect($this->request->fresh()->accepted_at)->toBeNull();
    checkAll($this->request, $this->registrar, CheckStage::Payment);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->call('approve')->assertHasErrors('officialReceiptNo')
        ->set('officialReceiptNo', 'OR-778899')->call('approve')->assertHasNoErrors();

    expect($this->request->paymentProofs()->sole())
        ->status->toBe(PaymentStatus::Verified)
        ->official_receipt_no->toBe('OR-778899')
        ->reviewed_by->toBe($this->registrar->id);
});

/** The requester's page with a new proof filled in, ready to upload. */
function newProof(): Testable
{
    return Livewire::actingAs(test()->owner)->test(RequesterShow::class, ['credentialRequest' => test()->request->fresh()])
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->set('paymentReference', 'UPHSL-456')
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

test('REQ-04-20 the registrar sees the note on Payment Validation, in the notification and in the email', function () {
    Notification::fake();
    uploadPayment($this->request);
    workflow()->rejectPayment($this->request, $this->registrar, 'The reference does not match our record.');
    newProof()->set('paymentNote', 'I paid at the bank; the reference is on the stamped slip.')->call('uploadReceipt')->assertHasNoErrors();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request->fresh()])
        ->assertSeeHtml('data-payment-note')
        ->assertSeeInOrder(["Requester's note:", 'I paid at the bank; the reference is on the stamped slip.', 'Proof of Payment']);

    Notification::assertSentTo($this->registrar, RequestActivity::class, function (RequestActivity $notification) {
        return $notification->event->type === RequestEventType::PaymentSubmitted
            && str_contains($notification->toArray($this->registrar)['message'], 'I paid at the bank')
            && str_contains(implode(' ', $notification->toMail($this->registrar)->introLines), 'Remarks: I paid at the bank');
    });

    // A first proof, sent without a return, shows no note box.
    $other = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($other);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $other])->assertDontSeeHtml('data-payment-note');
});

test('REQ-04-14 acceptance starts the clock: due date, route and first hand-off', function () {
    uploadPayment($this->request);
    checkAll($this->request, $this->registrar, CheckStage::Payment);
    Carbon::setTestNow('2026-10-09 10:00:00');   // a Friday
    workflow()->verifyPaymentAndAccept($this->request, $this->registrar, 'OR-1');

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
