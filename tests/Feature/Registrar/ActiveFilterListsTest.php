<?php

use App\Enums\RegistrarQueue;
use App\Livewire\Registrar\Logs\Index as Logs;
use App\Livewire\Registrar\Programs\Index as Programs;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as Records;
use App\Livewire\Registrar\Reports\Index as Reports;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Active']);
    $this->retiredCollege = Department::factory()->college()->inactive()->create(['name' => 'College of Law', 'code' => 'LAW']);
    $this->retiredOffice = Department::factory()->inactive()->create(['name' => 'Cashier', 'code' => 'FIN']);
    $this->retiredType = CredentialType::factory()->inactive()->create(['name' => 'Retired Credential']);
    $this->formerEvaluator = User::factory()->registrar()->inactive()->create(['first_name' => 'Former']);
});

/** @param  Collection<int, object>  $rows */
function listedNames(Collection $rows, string $key = 'name'): array
{
    return $rows->pluck($key)->all();
}

test('REQ-02-19 the In progress office filter lists only active offices', function () {
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => RegistrarQueue::InProgress->value])
        ->assertViewHas('offices', fn (Collection $offices) => in_array('College of Computer Studies', listedNames($offices), true)
            && ! in_array('College of Law', listedNames($offices), true)
            && ! in_array('Cashier', listedNames($offices), true))
        ->assertDontSee('College of Law');
});

test('REQ-02-19 the Records filters list only active offices and credential types', function () {
    Livewire::actingAs($this->registrar)->test(Records::class)
        ->assertViewHas('offices', fn (Collection $offices) => ! in_array('College of Law', listedNames($offices), true)
            && in_array('Office of the Chancellor', listedNames($offices), true))
        ->assertViewHas('types', fn (Collection $types) => ! in_array('Retired Credential', listedNames($types), true)
            && in_array('Transcript of Records', listedNames($types), true));
});

test('REQ-02-19 the Reports filters list only active offices, credential types and evaluators', function () {
    Livewire::actingAs($this->registrar)->test(Reports::class)
        ->assertViewHas('offices', fn (Collection $offices) => ! in_array('College of Law', listedNames($offices), true))
        ->assertViewHas('types', fn (Collection $types) => ! in_array('Retired Credential', listedNames($types), true))
        ->assertViewHas('evaluators', fn (Collection $evaluators) => in_array('Active', listedNames($evaluators, 'first_name'), true)
            && ! in_array('Former', listedNames($evaluators, 'first_name'), true));
});

test('REQ-02-19 the Activity log office filter and the Programs college list show only active ones', function () {
    Livewire::actingAs($this->registrar)->test(Logs::class)
        ->assertViewHas('offices', fn (Collection $offices) => ! in_array('College of Law', listedNames($offices), true)
            && ! in_array('Cashier', listedNames($offices), true));

    Livewire::actingAs($this->registrar)->test(Programs::class)
        ->assertViewHas('colleges', fn (Collection $colleges) => listedNames($colleges) === ['College of Business and Accountancy', 'College of Computer Studies']);
});

test('REQ-02-19 inactive rows stay on the Departments page, where they can be reactivated', function () {
    $this->actingAs($this->registrar)->get(route('registrar.departments'))->assertOk()->assertSee('College of Law');
});
