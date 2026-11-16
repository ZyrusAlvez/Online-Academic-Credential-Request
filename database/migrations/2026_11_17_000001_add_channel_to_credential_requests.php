<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each request was made, online or walk-in (spec 25): the Cashier validates online payments,
 * the Registrar walk-in ones. Every existing request was made online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->string('channel', 20)->default('online')->after('requester_mode');
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropIndex(['channel', 'status']);
            $table->dropColumn('channel');
        });
    }
};
