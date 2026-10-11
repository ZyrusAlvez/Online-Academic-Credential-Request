<?php

use App\Enums\Role;
use App\Livewire\Registrar\Users\Index;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Rita', 'last_name' => 'Registrar']);
    $this->library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
});

test('REQ-02-07 the registrar can list, search and filter accounts with departments', function () {
    User::factory()->departmentStaff($this->library)->create(['first_name' => 'Lina', 'middle_name' => null, 'last_name' => 'Librarian']);
    User::factory()->student()->create(['first_name' => 'Sam', 'middle_name' => null, 'last_name' => 'Student']);

    $this->actingAs($this->registrar)->get(route('registrar.users'))
        ->assertOk()->assertSee('Lina Librarian')->assertSee('Library')->assertSee('Sam Student');

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('role', 'student')
        ->assertSee('Sam Student')->assertDontSee('Lina Librarian')
        ->set('role', '')
        ->set('search', 'lina')
        ->assertSee('Lina Librarian')->assertDontSee('Sam Student');
});

test('REQ-02-08 the registrar can create a verified department staff account', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('create')
        ->set('form.first_name', 'Lina')
        ->set('form.last_name', 'Librarian')
        ->set('form.email', 'lina@uphsl.edu.ph')
        ->set('form.role', 'department')
        ->set('form.department_id', $this->library->id)
        ->set('form.password', 'secret123')
        ->call('save')
        ->assertHasNoErrors();

    $staff = User::where('email', 'lina@uphsl.edu.ph')->sole();
    expect($staff->role)->toBe(Role::Department)
        ->and($staff->department_id)->toBe($this->library->id)
        ->and($staff->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secret123', $staff->password))->toBeTrue();
});

test('REQ-02-08 a department is required for department staff and must be active', function () {
    $inactive = Department::factory()->inactive()->create();

    $component = Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('form.first_name', 'A')->set('form.last_name', 'B')
        ->set('form.email', 'ab@uphsl.edu.ph')->set('form.password', 'secret123')
        ->set('form.role', 'department');

    $component->call('save')->assertHasErrors(['form.department_id' => 'required']);
    $component->set('form.department_id', $inactive->id)->call('save')->assertHasErrors(['form.department_id' => 'exists']);
    $component->set('form.department_id', 'abc')->call('save')->assertHasErrors('form.department_id');
});

test('REQ-02-08 a registrar account has no department', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('form.first_name', 'New')->set('form.last_name', 'Registrar')
        ->set('form.email', 'new.registrar@uphsl.edu.ph')->set('form.password', 'secret123')
        ->set('form.role', 'registrar')
        ->set('form.department_id', $this->library->id)
        ->call('save')
        ->assertHasNoErrors();

    $account = User::where('email', 'new.registrar@uphsl.edu.ph')->sole();
    expect($account->role)->toBe(Role::Registrar)->and($account->department_id)->toBeNull();
});

test('REQ-02-09 the registrar can edit a staff account and optionally reset its password', function () {
    $staff = User::factory()->departmentStaff($this->library)->create();
    $cashier = Department::factory()->create(['code' => 'FIN']);
    $oldHash = $staff->password;

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('edit', $staff->id)
        ->set('form.last_name', 'Changed')
        ->set('form.department_id', $cashier->id)
        ->call('save')
        ->assertHasNoErrors();

    $staff->refresh();
    expect($staff->last_name)->toBe('Changed')
        ->and($staff->department_id)->toBe($cashier->id)
        ->and($staff->password)->toBe($oldHash);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('edit', $staff->id)
        ->set('form.password', 'brand-new-pass')
        ->call('save');

    expect(Hash::check('brand-new-pass', $staff->fresh()->password))->toBeTrue();
});

test('REQ-02-10 the registrar can deactivate and reactivate any account', function (string $state) {
    $account = User::factory()->{$state}()->create();

    $component = Livewire::actingAs($this->registrar)->test(Index::class)->call('toggleStatus', $account->id);
    expect($account->fresh()->is_active)->toBeFalse();

    $component->call('toggleStatus', $account->id);
    expect($account->fresh()->is_active)->toBeTrue();
})->with(['student', 'departmentStaff', 'registrar']);

test('REQ-02-10 the registrar cannot deactivate their own account', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('toggleStatus', $this->registrar->id)
        ->assertForbidden();

    expect($this->registrar->fresh()->is_active)->toBeTrue();
});

test('REQ-02-11 student accounts cannot be created or edited as staff', function () {
    $student = User::factory()->student()->create();

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('form.first_name', 'X')->set('form.last_name', 'Y')
        ->set('form.email', 'xy@example.com')->set('form.password', 'secret123')
        ->set('form.role', 'student')
        ->call('save')
        ->assertHasErrors(['form.role' => 'in']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('edit', $student->id)
        ->assertForbidden();
});

test('REQ-02-12 non-registrars cannot manage accounts', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('registrar.users'))->assertForbidden();

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff']);
