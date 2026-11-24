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

test('REQ-02-08 a staff account cannot reuse an email that is already registered', function () {
    $student = User::factory()->student()->create(['email' => 'juan@example.com']);
    $staff = User::factory()->departmentStaff($this->library)->create(['email' => 'lina@uphsl.edu.ph']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->set('form.first_name', 'Juan')->set('form.last_name', 'Again')
        ->set('form.email', 'juan@example.com')->set('form.role', 'registrar')
        ->set('form.password', 'Secret123!')
        ->call('save')
        ->assertHasErrors(['form.email' => 'unique']);

    // Editing another account to an email in use is refused; keeping its own email is not.
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $staff->id)
        ->set('form.email', 'juan@example.com')
        ->call('save')
        ->assertHasErrors(['form.email' => 'unique'])
        ->set('form.email', 'lina@uphsl.edu.ph')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'juan@example.com')->sole()->is($student))->toBeTrue();
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

    $this->actingAs($user)->get(route('admin.accounts'))->assertForbidden();

    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff', 'registrar']);
