<?php

use App\Enums\DepartmentType;
use App\Livewire\Registrar\Departments\Index;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-02-01 the seeder creates the 15 UPHSL departments without duplicates', function () {
    $this->seed(DepartmentSeeder::class);
    $this->seed(DepartmentSeeder::class);

    expect(Department::count())->toBe(15)
        ->and(Department::colleges()->count())->toBe(11)
        ->and(Department::where('type', DepartmentType::Office)->count())->toBe(4)
        ->and(Department::where('code', Department::REGISTRAR_CODE)->value('name'))->toBe('Office of the Registrar');
});

test('REQ-02-02 the registrar can list, search and filter departments', function () {
    Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
    Department::factory()->inactive()->create(['name' => 'Old Office', 'code' => 'OLD']);

    $this->actingAs($this->registrar)->get(route('registrar.departments'))
        ->assertOk()->assertSee('College of Computer Studies')->assertSee('Library');

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('search', 'ccs')
        ->assertSee('College of Computer Studies')->assertDontSee('Library')
        ->set('search', '')
        ->set('type', 'office')
        ->assertSee('Library')->assertDontSee('College of Computer Studies')
        ->set('status', 'inactive')
        ->assertSee('Old Office')->assertDontSee('Library');
});

test('REQ-02-03 the registrar can create a department with an upper-case unique code', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('create')
        ->set('form.name', 'Guidance Office')
        ->set('form.code', 'guid')
        ->set('form.type', 'office')
        ->set('form.description', 'Guidance clearance')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(Department::sole())
        ->name->toBe('Guidance Office')
        ->code->toBe('GUID')
        ->type->toBe(DepartmentType::Office);
});

test('REQ-02-03 department name and code must be unique', function () {
    Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('form.name', 'Library')
        ->set('form.code', 'lib')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique', 'form.code' => 'unique']);
});

test('REQ-02-04 the registrar can edit a department', function () {
    $department = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('edit', $department->id)
        ->assertSet('form.name', 'Library')
        ->set('form.name', 'University Library')
        ->set('form.type', 'office')
        ->call('save')
        ->assertHasNoErrors();

    expect($department->fresh()->name)->toBe('University Library')
        ->and(Department::count())->toBe(1);
});

test('REQ-02-05 the registrar can deactivate and reactivate a department', function () {
    $department = Department::factory()->create();

    $component = Livewire::actingAs($this->registrar)->test(Index::class)->call('toggleStatus', $department->id);
    expect($department->fresh()->is_active)->toBeFalse();

    $component->call('toggleStatus', $department->id);
    expect($department->fresh()->is_active)->toBeTrue();
});

test('REQ-02-05 departments cannot be deleted', function () {
    expect(method_exists(Index::class, 'delete'))->toBeFalse();
});

test('REQ-02-12 non-registrars cannot manage departments', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $department = Department::factory()->create();

    $this->actingAs($user)->get(route('registrar.departments'))->assertForbidden();

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff']);
