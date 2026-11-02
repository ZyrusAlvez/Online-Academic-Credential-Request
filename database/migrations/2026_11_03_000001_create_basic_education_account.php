<?php

use Database\Seeders\BasicEducationAccountSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the starter account reaches staging and production here
 * (REQ-12-11). Tests seed their own data, so the test runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new BasicEducationAccountSeeder)->run();
    }

    /**
     * The account may be in use by then.
     */
    public function down(): void
    {
        //
    }
};
