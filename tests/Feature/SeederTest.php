<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RegistrarSeeder;
use Illuminate\Support\Facades\Hash;

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
