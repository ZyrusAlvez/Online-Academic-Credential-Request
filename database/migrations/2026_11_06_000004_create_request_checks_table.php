<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Registrar's stage checklists (REQ-16-22): one row per part of a request that was confirmed or
 * rejected. A part confirmed from an earlier request's verification names that request (spec 21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->string('item', 30);
            $table->string('result', 20);
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at');
            $table->foreignId('carried_from_request_id')->nullable()->constrained('credential_requests')->nullOnDelete();

            $table->unique(['credential_request_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_checks');
    }
};
