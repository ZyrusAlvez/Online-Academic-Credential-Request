<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Details verified once (spec 21): the request on which each part of a requester's details was
 * verified, so later requests with the same details are confirmed from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requester_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('part', 30);
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');

            $table->unique(['user_id', 'part']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requester_verifications');
    }
};
