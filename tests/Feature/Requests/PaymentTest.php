<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Livewire\Student\Requests\Show;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
    $this->request = submitRequest($this->student, $this->org->tor, $this->org->ccs);
});

function uploadReceipt(?UploadedFile $file = null): Testable
{
    return Livewire::actingAs(test()->student)
        ->test(Show::class, ['credentialRequest' => test()->request])
        ->set('receipt', $file ?? UploadedFile::fake()->image('receipt.jpg'))
        ->set('paymentReference', 'OR-12345')
        ->set('amountPaid', '300')
        ->call('uploadReceipt');
}

test('REQ-04-05 awaiting-payment requests show the fee and the payment portal link', function () {
    $this->actingAs($this->student)->get(route('student.requests.show', $this->request))
        ->assertOk()
        ->assertSee('₱300.00')
        ->assertSee('href="'.config('uphsl.payment_url').'"', false)
        ->assertSee('target="_blank"', false);
});

test('REQ-04-06 the owner can upload a receipt which moves the request to payment review', function () {
    uploadReceipt()->assertHasNoErrors();

    $proof = $this->request->paymentProofs()->sole();
    expect($this->request->fresh()->status)->toBe(RequestStatus::PaymentReview)
        ->and($proof->status)->toBe(PaymentStatus::Submitted)
        ->and($proof->reference_no)->toBe('OR-12345')
        ->and($proof->amount)->toBe('300.00')
        ->and($this->request->events()->get()->last()->type)->toBe(RequestEventType::PaymentSubmitted);

    Storage::disk('local')->assertExists($proof->path);
});

test('REQ-04-06 receipts must be an image or PDF of at most 5 MB', function (UploadedFile $file) {
    uploadReceipt($file)->assertHasErrors('receipt');

    expect($this->request->paymentProofs()->count())->toBe(0);
})->with([
    'executable' => fn () => UploadedFile::fake()->create('receipt.exe', 10, 'application/x-msdownload'),
    'too large' => fn () => UploadedFile::fake()->image('receipt.png')->size(6000),
]);

test('REQ-04-06 receipts cannot be uploaded outside awaiting payment or by others', function () {
    uploadReceipt()->assertHasNoErrors();

    uploadReceipt()->assertForbidden();   // now in payment review

    Livewire::actingAs(User::factory()->student()->create())
        ->test(Show::class, ['credentialRequest' => $this->request])
        ->assertNotFound();
});

test('REQ-04-07 only the owner and registrars can view a receipt', function () {
    uploadReceipt();
    $proof = $this->request->paymentProofs()->sole();
    $url = route('payment-proofs.show', $proof);

    $this->actingAs($this->student)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->registrar)->get($url)->assertOk();
    $this->actingAs(User::factory()->student()->create())->get($url)->assertNotFound();
    $this->actingAs(User::factory()->departmentStaff($this->org->library)->create())->get($url)->assertNotFound();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

test('REQ-04-08 rejecting a payment requires remarks and returns the request to awaiting payment', function () {
    uploadReceipt();

    $review = Livewire::actingAs($this->registrar)->test(RegistrarShow::class, ['credentialRequest' => $this->request]);
    $review->call('rejectPayment')->assertHasErrors('rejectRemarks');
    $review->set('rejectRemarks', 'Amount does not match')->call('rejectPayment')->assertHasNoErrors();

    $proof = $this->request->paymentProofs()->sole();
    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($proof->status)->toBe(PaymentStatus::Rejected)
        ->and($proof->remarks)->toBe('Amount does not match')
        ->and($proof->reviewed_by)->toBe($this->registrar->id);

    $this->actingAs($this->student)->get(route('student.requests.show', $this->request))->assertSee('Amount does not match');

    $this->request->refresh();
    uploadReceipt()->assertHasNoErrors();
    expect($this->request->paymentProofs()->count())->toBe(2);
});
