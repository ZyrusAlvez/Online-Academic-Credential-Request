<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first admin account, the only account the normal seeding creates; the admin then
 * creates the registrar and department staff accounts (REQ-18-11). Runs on every seed but only
 * creates the account when it is missing.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('uphsl.initial_admin.email');
        $password = config('uphsl.initial_admin.password');

        if (blank($email)) {
            throw new RuntimeException('Set INITIAL_ADMIN_EMAIL in .env before seeding.');
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        if (blank($password)) {
            throw new RuntimeException('Set INITIAL_ADMIN_PASSWORD in .env to create the first admin.');
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
