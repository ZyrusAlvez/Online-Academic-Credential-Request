<?php

use App\Livewire\Registrar\Programs\Index;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ProgramSeeder;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create();
    $this->college = Department::factory()->college()->create(['name' => 'College of Arts and Sciences', 'code' => 'CAS']);
});

test('REQ-12-01 a name is unique within a college, not across colleges', function () {
    $program = Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']);

    expect(fn () => Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']))
        ->toThrow(QueryException::class);

    expect(Program::factory()->create(['name' => 'Bachelor of Arts'])->id)->not->toBe($program->id);
});

test('REQ-12-02 the seeder installs every college\'s programs without duplicates', function () {
    Department::query()->delete();
    $this->seed(DepartmentSeeder::class);

    $this->seed(ProgramSeeder::class);
    $count = Program::count();
    $this->seed(ProgramSeeder::class);

    expect($count)->toBe(27)
        ->and(Program::count())->toBe($count)
        ->and(Program::whereDoesntHave('college', fn ($query) => $query->colleges())->count())->toBe(0);
});

test('REQ-12-02 seeding again keeps what the registrar changed', function () {
    $program = Program::factory()->for($this->college, 'college')->inactive()->create(['name' => ProgramSeeder::PROGRAMS['CAS'][0]]);

    $this->seed(ProgramSeeder::class);

    expect($program->fresh()->is_active)->toBeFalse();
});

test('REQ-12-03 the registrar can search and filter programs', function () {
    Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts in Communication']);
    Program::factory()->for($this->college, 'college')->inactive()->create(['name' => 'Bachelor of Closed Studies']);
    $other = Program::factory()->create(['name' => 'Bachelor of Science in Biology']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->assertSee('Bachelor of Arts in Communication')
        ->assertSee('Bachelor of Science in Biology')
        ->set('search', 'Communication')
        ->assertSee('Bachelor of Arts in Communication')
        ->assertDontSee('Bachelor of Science in Biology')
        ->set('search', '')
        ->set('college', (string) $other->department_id)
        ->assertSee('Bachelor of Science in Biology')
        ->assertDontSee('Bachelor of Arts in Communication')
        ->set('college', '')
        ->set('status', 'inactive')
        ->assertSee('Bachelor of Closed Studies')
        ->assertDontSee('Bachelor of Arts in Communication');
});

test('REQ-12-03 the Programs page calls a college a department', function () {
    Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->assertSee('All departments')
        ->assertDontSee('All colleges')
        ->call('create')
        ->set('form.department_id', $this->college->id)
        ->set('form.name', 'Bachelor of Arts')
        ->call('save')
        ->assertHasErrors(['form.name' => 'This department already has a program with that name.']);
});

test('REQ-12-04 the registrar can add a program and edit it', function () {
    $component = Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('create')
        ->assertDispatched('modal-show', name: 'program-form')
        ->set('form.department_id', $this->college->id)
        ->set('form.name', 'Senior High School: STEM')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $program = Program::where('name', 'Senior High School: STEM')->firstOrFail();
    expect($program->department_id)->toBe($this->college->id)->and($program->is_active)->toBeTrue();

    $other = Department::factory()->college()->create();
    $component->call('edit', $program->id)
        ->assertSet('form.name', 'Senior High School: STEM')
        ->set('form.name', 'Senior High School: ABM')
        ->set('form.department_id', $other->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($program->fresh())->name->toBe('Senior High School: ABM')->department_id->toBe($other->id);
});

test('REQ-12-04 a program needs a name and a college, and no twin in that college', function () {
    Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']);
    $office = Department::factory()->create();

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('create')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required', 'form.department_id' => 'required'])
        ->set('form.department_id', $this->college->id)
        ->set('form.name', 'Bachelor of Arts')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique'])
        ->set('form.department_id', $office->id)
        ->set('form.name', 'Bachelor of Offices')
        ->call('save')
        ->assertHasErrors(['form.department_id' => 'exists']);

    expect(Program::count())->toBe(1);
});

test('REQ-12-05 the registrar deactivates and reactivates a program', function () {
    $program = Program::factory()->for($this->college, 'college')->create();

    $component = Livewire::actingAs($this->registrar)->test(Index::class)->call('toggleStatus', $program->id);
    expect($program->fresh()->is_active)->toBeFalse();

    $component->call('toggleStatus', $program->id);
    expect($program->fresh()->is_active)->toBeTrue();
});

test('REQ-12-06 only registrars can open the Programs page or change a program', function () {
    $program = Program::factory()->for($this->college, 'college')->create();

    $this->actingAs($this->registrar)->get(route('registrar.programs'))->assertOk()->assertSee('Programs');
    $this->actingAs(User::factory()->student()->create())->get(route('registrar.programs'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('registrar.programs'))->assertForbidden();
    $this->get(route('registrar.programs'))->assertForbidden();

    Livewire::actingAs(User::factory()->student()->create())->test(Index::class)->assertForbidden();
    expect($program->fresh()->is_active)->toBeTrue();
});
