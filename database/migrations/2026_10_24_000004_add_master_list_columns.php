<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credential master list (spec 03): documentary requirements, special conditions and the
 * receiving-school flag on credential types; a task on each route step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_types', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('processing_days');
            $table->text('special_conditions')->nullable()->after('requirements');
            $table->boolean('requires_receiving_institution')->default(false)->after('special_conditions');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('task', 20)->default('sign')->after('is_student_college');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('task');
        });

        Schema::table('credential_types', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'special_conditions', 'requires_receiving_institution']);
        });
    }
};
