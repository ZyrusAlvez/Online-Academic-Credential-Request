<?php

use App\Enums\IdentityStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = submitRequest($this->owner, $this->org->tor);
});

function registrarPage(): Testable
{
    return Livewire::actingAs(test()->registrar)->test(Show::class, ['credentialRequest' => test()->request]);
}

test('REQ-04-05 the registrar starts the evaluation, recording when and by whom', function () {
    registrarPage()->call('startEvaluation')->assertDispatched('notify');

    expect($this->request->fresh())
        ->status->toBe(RequestStatus::UnderEvaluation)
        ->evaluated_by->toBe($this->registrar->id)
        ->evaluation_started_at->not->toBeNull();
});

test('REQ-04-06 the evaluator sees the profile, identity and requirements', function () {
    workflow()->startEvaluation($this->request, $this->registrar);

    registrarPage()
        ->assertSee($this->owner->email)
        ->assertSee('Identity verified')
        ->assertSee('PSA-authenticated birth certificate')
        ->assertSee('Requirements complete – send payment instructions')
        ->assertSee('Requirements incomplete')
        ->assertSee('Ask the requester to clarify')
        ->assertSee('Not eligible – decline');
});

test('REQ-04-06 requirements incomplete lists the deficiencies', function () {
    workflow()->startEvaluation($this->request, $this->registrar);

    registrarPage()
        ->set('missing', ['PSA-authenticated birth certificate'])
        ->set('otherMissing', 'Affidavit of loss')
        ->call('markIncomplete')
        ->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::PendingCompliance)
        ->and($this->request->deficiencies()->pluck('description')->all())->toBe(['PSA-authenticated birth certificate', 'Affidavit of loss'])
        ->and($this->request->events()->get()->last()->type)->toBe(RequestEventType::ComplianceRequested);

    registrarPage()->set('missing', [])->set('otherMissing', '')->call('markIncomplete')->assertHasErrors('otherMissing');
});

test('REQ-04-06 clarification and decline need a message or reason', function () {
    workflow()->startEvaluation($this->request, $this->registrar);

    registrarPage()->call('requestAction')->assertHasErrors('actionMessage');
    registrarPage()->set('actionMessage', 'Confirm your name in 2021.')->call('requestAction')->assertHasNoErrors();
    expect($this->request->fresh()->status)->toBe(RequestStatus::ReturnedForCustomerAction);

    $other = submitRequest($this->owner, $this->org->free);
    workflow()->startEvaluation($other, $this->registrar);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $other])
        ->call('decline')->assertHasErrors('declineReason')
        ->set('declineReason', 'Not a UPHSL graduate.')->call('decline')->assertHasNoErrors();

    expect($other->fresh())
        ->status->toBe(RequestStatus::Declined)
        ->decline_reason->toBe('Not a UPHSL graduate.');
});

test('REQ-04-07 the requirements cannot be complete while the identity is unverified', function () {
    $this->owner->forceFill(['identity_status' => IdentityStatus::Pending])->save();
    workflow()->startEvaluation($this->request, $this->registrar);

    expect(fn () => workflow()->completeEvaluation($this->request, $this->registrar))
        ->toThrow(InvalidTransition::class, "Verify the requester's valid ID");
    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);
});

test('REQ-04-08 complete requirements send a paid request to payment and accept a free one', function () {
    evaluate($this->request, $this->registrar);
    expect($this->request->fresh())
        ->status->toBe(RequestStatus::AwaitingPayment)
        ->requirements_completed_at->not->toBeNull();

    $free = evaluate(submitRequest($this->owner, $this->org->free), $this->registrar);
    expect($free->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($free->events()->pluck('type')->all())->toContain(RequestEventType::RequirementsCompleted, RequestEventType::Accepted);
});

test('REQ-04-09 the requester sees each deficiency and the compliance notice', function () {
    workflow()->startEvaluation($this->request, $this->registrar);
    workflow()->requestCompliance($this->request, $this->registrar, ['Original Form 137']);

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Important notice: incomplete requirements')
        ->assertSee('Original Form 137')
        ->assertSee('Special Power of Attorney')
        ->assertSee('processing period starts only after');
});

test('REQ-04-10 compliance is recorded per deficiency and blocks payment until all are resolved', function () {
    workflow()->startEvaluation($this->request, $this->registrar);
    workflow()->requestCompliance($this->request, $this->registrar, ['Original Form 137', 'Library clearance']);
    [$form137, $clearance] = $this->request->deficiencies()->get()->all();

    expect(fn () => workflow()->completeEvaluation($this->request, $this->registrar))
        ->toThrow(InvalidTransition::class, 'every missing requirement');

    registrarPage()
        ->call('confirmResolve', $form137->id)
        ->call('resolveDeficiency')->assertHasErrors('resolution')
        ->set('resolution', 'Original received')
        ->call('resolveDeficiency')->assertHasNoErrors();

    expect($form137->fresh())
        ->resolved_by->toBe($this->registrar->id)
        ->resolution->toBe('Original received')
        ->resolved_at->not->toBeNull();

    workflow()->resolveDeficiency($clearance, $this->registrar, 'Clearance signed by the Library');
    workflow()->completeEvaluation($this->request, $this->registrar);

    expect($this->request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

test('REQ-04-11 the requester replies and the request returns to its previous stage', function () {
    workflow()->startEvaluation($this->request, $this->registrar);
    workflow()->requestCompliance($this->request, $this->registrar, ['Original Form 137']);
    workflow()->requestCustomerAction($this->request, $this->registrar, 'Which year did you graduate?');

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Which year did you graduate?')
        ->call('respond')->assertHasErrors('reply')
        ->set('reply', 'I graduated in 2023.')
        ->call('respond')->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::PendingCompliance)
        ->and($this->request->events()->get()->last()->remarks)->toBe('I graduated in 2023.');

    workflow()->requestCustomerAction($this->request, $this->registrar, 'Bring the original.');
    registrarPage()->set('compliedNotes', 'Came in person.')->call('customerComplied');
    expect($this->request->fresh()->status)->toBe(RequestStatus::PendingCompliance);
});
