<?php

use Database\Seeders\PurposeSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the starter list reaches staging and production here
 * (REQ-13-02). Tests seed their own data, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new PurposeSeeder)->run();
    }

    /**
     * The registrar may have edited the list since.
     */
    public function down(): void
    {
        //
    }
};
