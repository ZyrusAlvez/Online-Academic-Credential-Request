<?php

use App\Enums\DepartmentType;
use App\Models\Department;
use Database\Seeders\ProgramSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Deploys only run migrations, so the published list and the Basic Education Department reach
 * staging and production here (REQ-02-01, REQ-12-02). Only that office is added: running
 * DepartmentSeeder again would undo the registrar's edits. Tests seed their own data, so the test
 * runner skips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Department::firstOrCreate(['code' => 'BED'], [
            'name' => 'Basic Education Department',
            'type' => DepartmentType::College,
            'description' => "Dean's review and signature",
        ]);

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
