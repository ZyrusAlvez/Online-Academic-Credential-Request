<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin overrides (spec 35): every ledger entry an override writes keeps why, and when it was
 * recorded; its created_at is when the step happened (REQ-35-09, 10). Null on every other entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->string('override_reason', 500)->nullable()->after('remarks');
            $table->timestamp('recorded_at')->nullable()->after('override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->dropColumn(['override_reason', 'recorded_at']);
        });
    }
};
