<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\BasicEducationAccountSeeder;
use Database\Seeders\RegistrarSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

test('REQ-01-15 the seeder creates an active, verified registrar', function () {
    $this->seed(RegistrarSeeder::class);
    $this->seed(RegistrarSeeder::class); // idempotent

    $registrar = User::sole();
    expect($registrar->role)->toBe(Role::Registrar)
        ->and($registrar->is_active)->toBeTrue()
        ->and($registrar->hasVerifiedEmail())->toBeTrue()
        ->and($registrar->email)->toBe(config('uphsl.initial_registrar.email'));
});

test('REQ-01-15 seeding again never changes the existing registrar', function () {
    $this->seed(RegistrarSeeder::class);

    config(['uphsl.initial_registrar.password' => 'a-different-password']);
    $this->seed(RegistrarSeeder::class);

    expect(Hash::check('password', User::sole()->password))->toBeTrue();
});

test('REQ-01-15 the seeder refuses to create the registrar without a password', function () {
    config(['uphsl.initial_registrar.password' => null]);

    $this->seed(RegistrarSeeder::class);
})->throws(RuntimeException::class, 'INITIAL_REGISTRAR_PASSWORD');

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
