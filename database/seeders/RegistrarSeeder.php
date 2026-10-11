<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first registrar account so the system can be administered after install.
 */
class RegistrarSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('uphsl.initial_registrar.password');

        if (blank($password)) {
            throw new RuntimeException('Set INITIAL_REGISTRAR_PASSWORD in .env before setting up production.');
        }

        $registrar = User::firstOrNew(['email' => config('uphsl.initial_registrar.email')]);

        $registrar->fill([
            'first_name' => 'Office of the',
            'last_name' => 'Registrar',
            'password' => $password,
        ]);
        $registrar->forceFill([
            'role' => Role::Registrar,
            'is_active' => true,
            'email_verified_at' => $registrar->email_verified_at ?? now(),
        ])->save();
    }
}
