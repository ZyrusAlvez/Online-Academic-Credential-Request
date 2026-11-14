<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\BasicEducationAccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Password;

test('REQ-18-11 normal seeding creates the first admin and no other account', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::sole();
    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->email)->toBe(config('uphsl.initial_admin.email'))
        ->and(Department::count())->toBeGreaterThan(0);
});

test('REQ-12-11 the Basic Education Department gets a working staff account on the registrar inbox', function () {
    $office = Department::factory()->create(['code' => 'BED']);
    $registrar = User::factory()->create(['role' => Role::Registrar, 'email' => 'registrar@uphsl.edu.ph']);

    $this->seed(BasicEducationAccountSeeder::class);

    $account = User::where('email', 'registrar+bed@uphsl.edu.ph')->sole();
    expect($account->role)->toBe(Role::Department)
        ->and($account->department_id)->toBe($office->id)
        ->and($account->is_active)->toBeTrue()
        ->and($account->hasVerifiedEmail())->toBeTrue()
        ->and($registrar->fresh()->email)->toBe('registrar@uphsl.edu.ph');

    $this->actingAs($account)->get(route('department.dashboard'))->assertOk();
    expect(Password::sendResetLink(['email' => $account->email]))->toBe(Password::RESET_LINK_SENT);
});

test('REQ-12-11 the seeder leaves an office alone once it has staff', function () {
    $office = Department::factory()->create(['code' => 'BED']);
    User::factory()->create(['role' => Role::Registrar]);
    User::factory()->create(['role' => Role::Department, 'department_id' => $office->id]);

    $this->seed(BasicEducationAccountSeeder::class);

    expect(User::where('role', Role::Department)->count())->toBe(1);
});

test('REQ-12-11 the seeder does nothing before a registrar exists', function () {
    Department::factory()->create(['code' => 'BED']);

    $this->seed(BasicEducationAccountSeeder::class);

    expect(User::count())->toBe(0);
});
