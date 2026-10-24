<?php

use Database\Seeders\NonWorkingDaySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the holiday calendar reaches staging and production here
 * (spec 10, REQ-10-07). Tests build their own calendar, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new NonWorkingDaySeeder)->run();
    }

    /**
     * Holidays stay: the registrar may have edited them since.
     */
    public function down(): void
    {
        //
    }
};
