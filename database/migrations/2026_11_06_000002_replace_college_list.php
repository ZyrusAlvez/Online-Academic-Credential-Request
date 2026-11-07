<?php

use Database\Seeders\ReplaceCollegeListSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the 2026-11-06 college and program list reaches staging and
 * production here (REQ-02-14, REQ-12-12). Tests seed their own data, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new ReplaceCollegeListSeeder)->run();
    }

    /**
     * The earlier list can't be brought back: its staff accounts and programs are gone.
     */
    public function down(): void
    {
        //
    }
};
