<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = evaluate(submitRequest($this->owner, $this->org->tor, ['copies' => 2]), $this->registrar);
});

afterEach(fn () => Carbon::setTestNow());

test('REQ-04-12 the owner sees the amount due and uploads a proof with the transaction details', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Amount due: ₱1,060.00')
        ->assertSee(config('uphsl.payment_url'))
        ->set('receipt', UploadedFile::fake()->image('receipt.jpg'))
        ->set('paymentReference', 'UPHSL-123')
        ->set('amountPaid', '1060')
        ->set('paidOn', now()->format('Y-m-d'))
        ->call('uploadReceipt')
        ->assertHasNoErrors();

    $proof = $this->request->paymentProofs()->sole();

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($proof->path);
    expect($this->request->fresh()->status)->toBe(RequestStatus::PaymentVerification)
        ->and($proof)->reference_no->toBe('UPHSL-123')->amount->toBe('1060.00')->paid_on->toDateString()->toBe(now()->toDateString());
});

test('REQ-04-12 the proof must be an image or PDF of at most 5 MB with its details', function () {
    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->set('receipt', UploadedFile::fake()->create('receipt.exe', 100))
        ->call('uploadReceipt')
        ->assertHasErrors(['receipt' => 'mimes', 'paymentReference' => 'required', 'amountPaid' => 'required', 'paidOn' => 'required']);

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

test('REQ-04-13 the registrar rejects a proof with remarks', function () {
    uploadPayment($this->request);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->call('rejectPayment')->assertHasErrors('rejectRemarks')
        ->set('rejectRemarks', 'No matching transaction')->call('rejectPayment')->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($this->request->paymentProofs()->sole())->status->toBe(PaymentStatus::Rejected)->remarks->toBe('No matching transaction');
});

test('REQ-04-13 verification needs the official receipt number; a proof alone is not paid', function () {
    uploadPayment($this->request);
    expect($this->request->fresh()->accepted_at)->toBeNull();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->call('verifyPayment')->assertHasErrors('officialReceiptNo')
        ->set('officialReceiptNo', 'OR-778899')->call('verifyPayment')->assertHasNoErrors();

    expect($this->request->paymentProofs()->sole())
        ->status->toBe(PaymentStatus::Verified)
        ->official_receipt_no->toBe('OR-778899')
        ->reviewed_by->toBe($this->registrar->id);
});

test('REQ-04-14 acceptance starts the clock: due date, route and first hand-off', function () {
    uploadPayment($this->request);
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
        ->and(openVisit($request)->status)->toBe(VisitStatus::InTransit)
        ->and($request->events()->pluck('type')->all())->toContain(RequestEventType::PaymentVerified, RequestEventType::Accepted, RequestEventType::DocumentForwarded);
});

test('REQ-04-15 the registrar can correct the college only before acceptance', function () {
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->set('collegeId', $this->org->cba->id)
        ->call('changeCollege');

    expect($this->request->fresh()->college_id)->toBe($this->org->cba->id)
        ->and($this->request->events()->get()->last()->type)->toBe(RequestEventType::CollegeChanged);

    uploadPayment($this->request);
    workflow()->verifyPaymentAndAccept($this->request, $this->registrar, 'OR-1');

    expect($this->request->fresh()->steps()->pluck('department_id')->all())->toContain($this->org->cba->id)
        ->and(fn () => workflow()->changeCollege($this->request, $this->registrar, $this->org->ccs))->toThrow(InvalidTransition::class);
});
