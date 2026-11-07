<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Livewire\Registrar\Requests\Show;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| The evaluation's checklist and decisions are spec 16 (RegistrarStagesTest).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = submitRequest($this->owner, $this->org->tor);
});

test('REQ-04-05 the registrar starts the evaluation, recording when and by whom', function () {
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->assertSee('Start evaluation')
        ->call('startEvaluation')->assertDispatched('notify');

    expect($this->request->fresh())
        ->status->toBe(RequestStatus::UnderEvaluation)
        ->evaluated_by->toBe($this->registrar->id)
        ->evaluation_started_at->not->toBeNull();
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
