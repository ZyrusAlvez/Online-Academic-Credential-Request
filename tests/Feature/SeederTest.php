<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RegistrarSeeder;

test('REQ-01-15 the seeder creates an active, verified registrar', function () {
    $this->seed(RegistrarSeeder::class);
    $this->seed(RegistrarSeeder::class); // idempotent

    $registrar = User::sole();
    expect($registrar->role)->toBe(Role::Registrar)
        ->and($registrar->is_active)->toBeTrue()
        ->and($registrar->hasVerifiedEmail())->toBeTrue();
});
