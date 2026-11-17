<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The requester's sex and, for a woman, her maiden name (spec 28): on the saved profile and on each
 * request's own copy. Empty on accounts and requests made before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('last_name');
            $table->string('maiden_name', 100)->nullable()->after('sex');
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('requester_name');
            $table->string('maiden_name', 100)->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropColumn(['sex', 'maiden_name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sex', 'maiden_name']);
        });
    }
};
