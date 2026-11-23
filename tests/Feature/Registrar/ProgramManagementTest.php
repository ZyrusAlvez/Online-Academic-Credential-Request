<?php

use App\Livewire\Admin\Programs\Index;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();   // programs are the Admin's (REQ-18-04)
    $this->college = Department::factory()->college()->create(['name' => 'College of Arts and Sciences', 'code' => 'CAS']);
});

test('REQ-12-01 a name is unique within a college, not across colleges', function () {
    $program = Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']);

    expect(fn () => Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']))
        ->toThrow(QueryException::class);

    expect(Program::factory()->create(['name' => 'Bachelor of Arts'])->id)->not->toBe($program->id);
});

test('REQ-12-04 a program needs a name and a college, and no twin in that college', function () {
    Program::factory()->for($this->college, 'college')->create(['name' => 'Bachelor of Arts']);
    $office = Department::factory()->create();

    Livewire::actingAs($this->admin)->test(Index::class)
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

test('REQ-12-06 only registrars can open the Programs page or change a program', function () {
    $program = Program::factory()->for($this->college, 'college')->create();

    $this->actingAs($this->admin)->get(route('admin.programs'))->assertOk()->assertSee('Programs');
    $this->actingAs(User::factory()->student()->create())->get(route('admin.programs'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('admin.programs'))->assertForbidden();
    $this->actingAs(User::factory()->registrar()->create())->get(route('admin.programs'))->assertForbidden();
    $this->get(route('admin.programs'))->assertForbidden();

    Livewire::actingAs(User::factory()->student()->create())->test(Index::class)->assertForbidden();
    expect($program->fresh()->is_active)->toBeTrue();
});
