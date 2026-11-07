<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first admin account so the system can be administered after install (REQ-18-11).
 * Runs on every seed but only creates the account when it is missing. Without the settings it
 * skips with a warning, so an install that has not added them yet still seeds.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('uphsl.initial_admin.email');
        $password = config('uphsl.initial_admin.password');

        if (blank($email)) {
            $this->command->warn('INITIAL_ADMIN_EMAIL is not set: no admin account was created.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        if (blank($password)) {
            $this->command->warn('INITIAL_ADMIN_PASSWORD is not set: no admin account was created.');

            return;
        }

        (new User)->fill([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => $email,
            'password' => $password,
        ])->forceFill([
            'role' => Role::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
