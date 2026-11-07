<?php

use Database\Seeders\RemoveCollegeOfLawSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The 2026-11-06 replacement only deactivated the College of Law where something still named it;
 * this deletes it with everything that names it (REQ-02-18). Tests seed their own data, so the
 * test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new RemoveCollegeOfLawSeeder)->run();
    }

    /**
     * What was deleted can't be brought back.
     */
    public function down(): void
    {
        //
    }
};
