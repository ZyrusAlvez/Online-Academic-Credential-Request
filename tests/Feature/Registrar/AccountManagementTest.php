<?php

use App\Actions\Staff\ChangeAccountRole;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    // Accounts are the Admin's (REQ-18-04).
    $this->admin = User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
    $this->library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
});

test('REQ-02-07 the admin can list, search and filter accounts with departments', function () {
    User::factory()->departmentStaff($this->library)->create(['first_name' => 'Lina', 'middle_name' => null, 'last_name' => 'Librarian']);
    // A fixed email: the search also looks there, and a random one can contain "lina".
    User::factory()->student()->create(['first_name' => 'Sam', 'middle_name' => null, 'last_name' => 'Student', 'email' => 'sam@example.com']);

    $this->actingAs($this->admin)->get(route('admin.users'))
        ->assertOk()->assertSee('Lina Librarian')->assertSee('Library')->assertSee('Sam Student');

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('role', 'student')
        ->assertSee('Sam Student')->assertDontSee('Lina Librarian')
        ->set('role', '')
        ->set('search', 'lina')
        ->assertSee('Lina Librarian')->assertDontSee('Sam Student');
});

test('REQ-02-07 the account search matches a first and last name typed together', function () {
    User::factory()->student()->create(['first_name' => 'Sam', 'middle_name' => null, 'last_name' => 'Student', 'email' => 'sam@example.com']);
    User::factory()->student()->create(['first_name' => 'Samuel', 'middle_name' => null, 'last_name' => 'Reyes', 'email' => 'samuel@example.com']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('search', 'sam stu')
        ->assertSee('Sam Student')->assertDontSee('Samuel Reyes')
        ->set('search', 'Samuel Reyes')
        ->assertSee('Samuel Reyes')->assertDontSee('Sam Student');
});

test('REQ-02-08 the admin can create a verified department staff account', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->set('form.first_name', 'Lina')
        ->set('form.last_name', 'Librarian')
        ->set('form.email', 'lina@uphsl.edu.ph')
        ->set('form.role', 'department')
        ->set('form.department_id', $this->library->id)
        ->set('form.password', 'Secret123!')
        ->call('save')
        ->assertHasNoErrors();

    $staff = User::where('email', 'lina@uphsl.edu.ph')->sole();
    expect($staff->role)->toBe(Role::Department)
        ->and($staff->department_id)->toBe($this->library->id)
        ->and($staff->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('Secret123!', $staff->password))->toBeTrue();
});

test('REQ-01-03 a staff account password must meet the password rules', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->set('form.first_name', 'Lina')->set('form.last_name', 'Librarian')
        ->set('form.email', 'lina@uphsl.edu.ph')->set('form.role', 'registrar')
        ->set('form.password', 'secret123')
        ->call('save')
        ->assertHasErrors(['form.password']);

    expect(User::where('email', 'lina@uphsl.edu.ph')->exists())->toBeFalse();
});

test('REQ-02-08 a department is required for department staff and must be active', function () {
    $inactive = Department::factory()->inactive()->create();

    $component = Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.first_name', 'A')->set('form.last_name', 'B')
        ->set('form.email', 'ab@uphsl.edu.ph')->set('form.password', 'Secret123!')
        ->set('form.role', 'department');

    $component->call('save')->assertHasErrors(['form.department_id' => 'required']);
    $component->set('form.department_id', $inactive->id)->call('save')->assertHasErrors(['form.department_id' => 'exists']);
    $component->set('form.department_id', 'abc')->call('save')->assertHasErrors('form.department_id');
});

test('REQ-02-08 a registrar account has no department', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.first_name', 'New')->set('form.last_name', 'Registrar')
        ->set('form.email', 'new.registrar@uphsl.edu.ph')->set('form.password', 'Secret123!')
        ->set('form.role', 'registrar')
        ->set('form.department_id', $this->library->id)
        ->call('save')
        ->assertHasNoErrors();

    $account = User::where('email', 'new.registrar@uphsl.edu.ph')->sole();
    expect($account->role)->toBe(Role::Registrar)->and($account->department_id)->toBeNull();
});

test('REQ-02-09 the admin can edit a staff account and optionally reset its password', function () {
    $staff = User::factory()->departmentStaff($this->library)->create();
    $cashier = Department::factory()->create(['code' => 'FIN']);
    $oldHash = $staff->password;

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $staff->id)
        ->set('form.last_name', 'Changed')
        ->set('form.department_id', $cashier->id)
        ->call('save')
        ->assertHasNoErrors();

    $staff->refresh();
    expect($staff->last_name)->toBe('Changed')
        ->and($staff->department_id)->toBe($cashier->id)
        ->and($staff->password)->toBe($oldHash);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $staff->id)
        ->set('form.password', 'Brand-new-pass1')
        ->call('save');

    expect(Hash::check('Brand-new-pass1', $staff->fresh()->password))->toBeTrue();
});

test('REQ-02-10 the admin can deactivate and reactivate any account', function (string $state) {
    $account = User::factory()->{$state}()->create();

    $component = Livewire::actingAs($this->admin)->test(Index::class)->call('toggleStatus', $account->id);
    expect($account->fresh()->is_active)->toBeFalse();

    $component->call('toggleStatus', $account->id);
    expect($account->fresh()->is_active)->toBeTrue();
})->with(['student', 'departmentStaff', 'registrar', 'admin']);

test('REQ-02-10 the admin cannot deactivate their own account', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('toggleStatus', $this->admin->id)
        ->assertForbidden();

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

test('REQ-02-09 editing a staff account keeps its role', function () {
    $staff = User::factory()->departmentStaff($this->library)->create();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $staff->id)
        ->assertDontSee('wire:model.live="form.role"', escape: false)
        ->set('form.role', 'registrar')
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->role)->toBe(Role::Department)
        ->and($staff->fresh()->department_id)->toBe($this->library->id);
});

test('REQ-02-11 student accounts cannot be created or edited as staff', function () {
    $student = User::factory()->student()->create();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.first_name', 'X')->set('form.last_name', 'Y')
        ->set('form.email', 'xy@example.com')->set('form.password', 'Secret123!')
        ->set('form.role', 'student')
        ->call('save')
        ->assertHasErrors(['form.role' => 'in']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $student->id)
        ->assertForbidden();
});

test('REQ-02-15 the admin can change an account to any other role', function (string $from, string $to) {
    $account = User::factory()->{$from === 'department' ? 'departmentStaff' : $from}()->create();
    $department = $to === 'department' ? $this->library->id : null;

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $account->id)
        ->assertSet('roleForm.role', $from)
        ->set('roleForm.role', $to)
        ->set('roleForm.department_id', $department)
        ->call('changeRole')
        ->assertHasNoErrors();

    $account->refresh();
    expect($account->role)->toBe(Role::from($to))
        ->and($account->department_id)->toBe($department);

    // The new role applies on the next page, without signing them out.
    $this->actingAs($account)->get(route('dashboard'))->assertRedirect(route($account->role->homeRoute()));
})->with([
    ['student', 'department'],
    ['student', 'registrar'],
    ['department', 'student'],
    ['department', 'registrar'],
    ['registrar', 'student'],
    ['registrar', 'department'],
    ['department', 'admin'],     // REQ-18-09
    ['admin', 'registrar'],
]);

test('REQ-02-15 a department staff member can move to another department', function () {
    $staff = User::factory()->departmentStaff($this->library)->create();
    $other = Department::factory()->create();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $staff->id)
        ->set('roleForm.department_id', $other->id)
        ->call('changeRole')
        ->assertHasNoErrors();

    expect($staff->fresh()->department_id)->toBe($other->id);
});

test('REQ-02-15 the department role needs an active department and a change', function () {
    $student = User::factory()->student()->create();
    $inactive = Department::factory()->inactive()->create();

    $component = Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $student->id)
        ->set('roleForm.role', 'department');

    $component->call('changeRole')->assertHasErrors(['roleForm.department_id' => 'required']);
    $component->set('roleForm.department_id', $inactive->id)->call('changeRole')->assertHasErrors(['roleForm.department_id' => 'exists']);
    $component->set('roleForm.role', 'nope')->call('changeRole')->assertHasErrors('roleForm.role');
    $component->set('roleForm.role', 'student')->call('changeRole')->assertHasErrors('roleForm.role');

    expect($student->fresh()->role)->toBe(Role::Student);
});

test('REQ-02-15 the admin cannot change their own role', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertDontSee('editRole('.$this->admin->id.')')
        ->call('editRole', $this->admin->id)
        ->assertForbidden();

    expect(fn () => app(ChangeAccountRole::class)->handle($this->admin, $this->admin, Role::Student))
        ->toThrow(DomainException::class);
    expect($this->admin->fresh()->role)->toBe(Role::Admin);
});

test('REQ-02-15 only the admin can change roles', function () {
    $staff = User::factory()->departmentStaff($this->library)->create();
    $registrar = User::factory()->registrar()->create();
    $other = User::factory()->student()->create();

    Livewire::actingAs($staff)->test(Index::class)->assertForbidden();
    Livewire::actingAs($registrar)->test(Index::class)->assertForbidden();
    expect($staff->can('changeRole', $other))->toBeFalse()
        ->and($registrar->can('changeRole', $other))->toBeFalse();
});

test('REQ-02-16 a student with an open request cannot change role', function () {
    $student = User::factory()->student()->create();
    CredentialRequest::factory()->for($student, 'student')->status(RequestStatus::AwaitingPayment)->create();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $student->id)
        ->set('roleForm.role', 'registrar')
        ->call('changeRole')
        ->assertHasErrors('roleForm.role')
        ->assertSee('must be released, declined or cancelled');

    expect($student->fresh()->role)->toBe(Role::Student);
});

test('REQ-02-16 a student whose requests are all finished can change role', function () {
    $student = User::factory()->student()->create();
    CredentialRequest::factory()->for($student, 'student')->status(RequestStatus::Released)->create();
    CredentialRequest::factory()->for($student, 'student')->status(RequestStatus::Cancelled)->create();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $student->id)
        ->set('roleForm.role', 'registrar')
        ->call('changeRole')
        ->assertHasNoErrors();

    expect($student->fresh()->role)->toBe(Role::Registrar);
});

test('REQ-02-17 a role change is logged with the old and new role', function () {
    $staff = User::factory()->departmentStaff($this->library)->create(['first_name' => 'Lina', 'middle_name' => null, 'last_name' => 'Librarian', 'email' => 'lina@uphsl.edu.ph']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('editRole', $staff->id)
        ->set('roleForm.role', 'registrar')
        ->call('changeRole');

    $log = ActivityLog::where('action', 'user.updated')->latest('id')->first();
    expect($log->description)->toBe('Changed the role of Lina Librarian (lina@uphsl.edu.ph) to Registrar')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->changes['role'])->toBe(['Department Staff', 'Registrar'])
        ->and($log->changes['department_id'])->toBe(['Library', null]);
});

test('REQ-02-12 non-registrars cannot manage accounts', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff', 'registrar']);
