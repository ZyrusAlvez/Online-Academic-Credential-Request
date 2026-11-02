<?php

use Database\Seeders\ProgramSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the published list reaches staging and production here
 * (REQ-12-02). Tests seed their own data, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new ProgramSeeder)->run();
    }

    /**
     * The registrar may have edited the list since.
     */
    public function down(): void
    {
        //
    }
};
