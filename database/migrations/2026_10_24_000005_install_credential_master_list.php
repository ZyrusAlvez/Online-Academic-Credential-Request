<?php

use Database\Seeders\CredentialTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the new offices and the 17-type master list reach staging and
 * production here, replacing the placeholder types (specs 02 and 03, REQ-03-12). Tests seed
 * their own data, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new DepartmentSeeder)->run();
        (new CredentialTypeSeeder)->install();
    }

    /**
     * The registrar may have edited the master list since.
     */
    public function down(): void
    {
        //
    }
};
