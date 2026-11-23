<?php

use App\Livewire\Admin\Departments\Index;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();   // offices are the Admin's (REQ-18-04)
});

test('REQ-02-03 department name and code must be unique', function () {
    Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.name', 'Library')
        ->set('form.code', 'lib')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique', 'form.code' => 'unique']);
});

test('REQ-02-05 departments cannot be deleted', function () {
    expect(method_exists(Index::class, 'delete'))->toBeFalse();
});

test('REQ-02-12 non-registrars cannot manage departments', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $department = Department::factory()->create();

    $this->actingAs($user)->get(route('admin.departments'))->assertForbidden();

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff', 'registrar']);
