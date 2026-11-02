<?php

use App\Enums\RouteTask;
use App\Livewire\Registrar\CredentialTypes\Workflow;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create();
    $this->library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
    $this->cashier = Department::factory()->create(['name' => 'Cashier', 'code' => 'FIN']);
    $this->registrarOffice = Department::factory()->registrarOffice()->create();
    $this->type = CredentialType::factory()->create();
});

function stepLabels(CredentialType $type): array
{
    return $type->steps()->with('department')->get()->map(fn ($s) => $s->position.':'.$s->label())->all();
}

function builder(): Testable
{
    return Livewire::actingAs(test()->registrar)->test(Workflow::class, ['credentialType' => test()->type]);
}

test('REQ-03-06 the registrar can append offices and the Dean step, each with a task', function () {
    builder()
        ->set('newStep', (string) $this->library->id)->set('newTask', 'prepare')->set('newInstructions', 'Check unreturned books')->call('addStep')
        ->assertHasNoErrors()
        ->set('newStep', 'college')->call('addStep')
        ->set('newStep', (string) $this->registrarOffice->id)->set('newTask', 'documentary_stamp')->call('addStep')
        ->assertHasNoErrors();

    $steps = $this->type->steps()->get();

    expect(stepLabels($this->type))->toBe(['1:Library', "2:Dean (student's college)", '3:Office of the Registrar'])
        ->and($steps->map->task->all())->toBe([RouteTask::Prepare, RouteTask::Sign, RouteTask::DocumentaryStamp])
        ->and($steps->first()->instructions)->toBe('Check unreturned books');
});

test('REQ-03-06 inactive departments cannot be added', function () {
    $inactive = Department::factory()->inactive()->create();

    builder()->set('newStep', (string) $inactive->id)->call('addStep')->assertHasErrors('newStep');

    expect($this->type->steps()->count())->toBe(0);
});

test('REQ-03-07 duplicates and a second Dean step are rejected', function () {
    builder()
        ->set('newStep', 'college')->call('addStep')
        ->set('newStep', (string) $this->library->id)->call('addStep')
        ->set('newStep', 'college')->call('addStep')->assertHasErrors('newStep')
        ->set('newStep', (string) $this->library->id)->call('addStep')->assertHasErrors('newStep');

    expect($this->type->steps()->count())->toBe(2);
});

test('REQ-03-08 steps can be moved and removed with contiguous positions', function () {
    $type = CredentialType::factory()->withSteps([$this->library, $this->cashier, 'college', $this->registrarOffice])->create();
    $this->type = $type;
    $steps = $type->steps()->get();

    builder()
        ->call('move', $steps[2]->id, -1)   // college up
        ->call('move', $steps[0]->id, -1)   // first step up: no-op
        ->call('move', $steps[3]->id, 1);   // last step down: no-op

    expect(stepLabels($type))->toBe(['1:Library', "2:Dean (student's college)", '3:Cashier', '4:Office of the Registrar']);

    builder()->call('remove', $steps[0]->id);

    expect(stepLabels($type))->toBe(["1:Dean (student's college)", '2:Cashier', '3:Office of the Registrar']);
});

test('REQ-03-08 steps of another credential type cannot be touched', function () {
    $other = CredentialType::factory()->withSteps([$this->library])->create();

    builder()->call('remove', $other->steps()->first()->id)->assertNotFound();

    expect($other->steps()->count())->toBe(1);
});

test('REQ-03-09 the registrar can edit a step\'s task and instructions', function () {
    $this->type = CredentialType::factory()->withSteps([$this->library])->create();
    $step = $this->type->steps()->first();

    builder()
        ->call('editStep', $step->id)
        ->assertSet('editingTask', 'sign')
        ->set('editingTask', 'documentary_stamp')
        ->set('editingInstructions', 'Verify clearance card')
        ->call('saveStep')
        ->assertHasNoErrors()
        ->assertSet('editingStepId', null);

    expect($step->fresh())
        ->task->toBe(RouteTask::DocumentaryStamp)
        ->instructions->toBe('Verify clearance card');
});

test('REQ-03-11 non-registrars cannot use the route builder', function () {
    Livewire::actingAs(User::factory()->departmentStaff()->create())
        ->test(Workflow::class, ['credentialType' => $this->type])
        ->assertForbidden();
});
