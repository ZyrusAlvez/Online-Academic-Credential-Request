<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the requester removed an ID from their profile; it stays only for the requests that use it
 * (REQ-15-18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
