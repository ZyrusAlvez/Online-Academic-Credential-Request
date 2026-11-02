<?php

namespace Database\Seeders;

use App\Actions\Staff\SaveStaffAccount;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Gives the Basic Education Department a starter staff account (REQ-12-11). The repo is public, so
 * the address reuses the first registrar's inbox and the password stays unknown until Forgot
 * Password replaces it. Does nothing when the office already has staff.
 */
class BasicEducationAccountSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = User::where('role', Role::Registrar)->oldest('id')->first();
        $office = Department::where('code', 'BED')->first();

        if ($registrar === null || $office === null) {
            return;
        }

        if (User::where('role', Role::Department)->where('department_id', $office->id)->exists()) {
            return;
        }

        (new SaveStaffAccount)->handle($registrar, [
            'first_name' => 'Basic Education',
            'middle_name' => null,
            'last_name' => 'Staff',
            'email' => Str::replaceLast('@', '+bed@', $registrar->email),
            'role' => Role::Department,
            'department_id' => $office->id,
            'password' => Str::random(64),
        ]);
    }
}
