<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A woman now gives her maiden middle and last names in the name fields themselves, so the separate
 * maiden name goes, with its values (REQ-28-04, user's decision 2026-11-20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropColumn('maiden_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('maiden_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('maiden_name', 100)->nullable()->after('sex');
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->string('maiden_name', 100)->nullable()->after('sex');
        });
    }
};
