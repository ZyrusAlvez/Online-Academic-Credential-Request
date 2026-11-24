<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Walk-in requests (spec 26): recorded by the Registrar for a client who may have no account yet,
 * linked to one later by email; their payment is validated at the counter, with no file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('requester_email')->nullable()->after('contact_number');
            $table->foreignId('recorded_by')->nullable()->after('channel')->constrained('users')->restrictOnDelete();
            $table->index(['requester_email', 'user_id']);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
            $table->string('mime_type', 100)->nullable()->change();
            $table->unsignedInteger('size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
            $table->string('mime_type', 100)->nullable(false)->change();
            $table->unsignedInteger('size')->nullable(false)->change();
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropIndex(['requester_email', 'user_id']);
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn('requester_email');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
