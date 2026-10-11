<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Index;
use App\Livewire\Registrar\Requests\Show;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->student = User::factory()->student()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz']);
    $this->registrar = User::factory()->registrar()->create();
    $this->workflow = app(RequestWorkflow::class);
});

function payAndUpload($request): void
{
    test()->workflow->submitPaymentProof($request, test()->student, UploadedFile::fake()->image('r.jpg'));
}

test('REQ-04-09 the registrar sees requests needing review and can search them', function () {
    $free = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $paid = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    $awaiting = submitRequest(User::factory()->student()->create(), $this->org->tor, $this->org->cba);
    payAndUpload($paid);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->assertSee($free->reference_no)
        ->assertSee($paid->reference_no)
        ->assertDontSee($awaiting->reference_no)
        ->set('status', '')
        ->set('search', 'Juan Cruz')
        ->assertSee($free->reference_no)
        ->assertDontSee($awaiting->reference_no);

    $this->actingAs($this->student)->get(route('registrar.requests'))->assertForbidden();
});

test('REQ-04-10 the registrar can correct the college before routing', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->set('collegeId', $this->org->cba->id)
        ->call('changeCollege')
        ->assertHasNoErrors();

    expect($request->fresh()->college_id)->toBe($this->org->cba->id)
        ->and($request->events()->get()->last())
        ->type->toBe(RequestEventType::CollegeChanged)
        ->remarks->toBe('From College of Computer Studies to College of Business and Accountancy.');
});

test('REQ-04-10 the college cannot be changed after routing', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);

    expect(fn () => $this->workflow->changeCollege($request, $this->registrar, $this->org->cba))
        ->toThrow(InvalidTransition::class);
});

test('REQ-04-11 accepting a payment snapshots the workflow and routes to step 1', function () {
    $request = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    payAndUpload($request);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->set('remarks', 'Receipt matches')
        ->call('approve')
        ->assertDispatched('notify');

    $request->refresh();
    $steps = $request->steps()->with('department')->get();

    expect($request->status)->toBe(RequestStatus::InProgress)
        ->and($request->routed_at)->not->toBeNull()
        ->and($request->paymentProofs()->sole()->status)->toBe(PaymentStatus::Accepted)
        ->and($steps->map(fn ($s) => $s->department->code)->all())->toBe(['LIB', 'FIN', 'CCS', 'REG'])
        ->and($steps->map(fn ($s) => $s->status)->all())->toBe([StepStatus::Pending, StepStatus::Waiting, StepStatus::Waiting, StepStatus::Waiting])
        ->and($request->events()->pluck('type')->all())->toBe([
            RequestEventType::Submitted, RequestEventType::PaymentSubmitted, RequestEventType::PaymentAccepted, RequestEventType::Routed,
        ]);
});

test('REQ-04-11 approving a free request routes it and uses only the student\'s college', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);

    expect($request->fresh()->status)->toBe(RequestStatus::InProgress)
        ->and($request->steps()->pluck('department_id')->all())->toBe([$this->org->registrarOffice->id]);
});

test('REQ-03-10 workflow edits after routing do not change routed requests', function () {
    $request = submitRequest($this->student, $this->org->tor, $this->org->cba);
    payAndUpload($request);
    $this->workflow->approve($request, $this->registrar);

    $this->org->tor->steps()->first()->delete();
    $this->org->tor->resequenceSteps();

    expect($request->steps()->count())->toBe(4);
});

test('REQ-04-12 approval is refused when the credential type has no workflow', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->org->free->steps()->delete();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request])
        ->call('approve')
        ->assertDispatched('notify', message: 'This credential type has no workflow steps. Add steps before approving.');

    expect($request->fresh()->status)->toBe(RequestStatus::RegistrarReview)
        ->and($request->steps()->count())->toBe(0);
});

test('REQ-04-15 requests awaiting payment cannot be approved', function () {
    $request = submitRequest($this->student, $this->org->tor, $this->org->ccs);

    expect(fn () => $this->workflow->approve($request, $this->registrar))->toThrow(InvalidTransition::class);
    expect($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($request->events()->count())->toBe(1);
});

test('non-registrars cannot open the review page', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);

    $this->actingAs($this->student)->get(route('registrar.requests.show', $request))->assertForbidden();
    Livewire::actingAs($this->student)->test(Show::class, ['credentialRequest' => $request])->assertForbidden();
});
