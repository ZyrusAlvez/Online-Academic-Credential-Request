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
        $password = config('uphsl.seed.registrar_password');

        if (blank($password)) {
            if (app()->isProduction()) {
                throw new RuntimeException('Set SEED_REGISTRAR_PASSWORD before seeding in production.');
            }

            $password = 'password';
        }

        $registrar = User::firstOrNew(['email' => config('uphsl.seed.registrar_email')]);

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
