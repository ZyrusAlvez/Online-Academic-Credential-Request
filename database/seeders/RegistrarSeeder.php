<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first registrar account so the system can be administered after install.
 * Runs on every seed but only creates the account when it is missing.
 */
class RegistrarSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('uphsl.initial_registrar.email');
        $password = config('uphsl.initial_registrar.password');

        if (blank($email)) {
            throw new RuntimeException('Set INITIAL_REGISTRAR_EMAIL in .env before seeding.');
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        if (blank($password)) {
            throw new RuntimeException('Set INITIAL_REGISTRAR_PASSWORD in .env to create the first registrar.');
        }

        (new User)->fill([
            'first_name' => 'Office of the',
            'last_name' => 'Registrar',
            'email' => $email,
            'password' => $password,
        ])->forceFill([
            'role' => Role::Registrar,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
