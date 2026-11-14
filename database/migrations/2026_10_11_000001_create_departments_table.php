<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colleges (each acting as the Dean) and the offices a credential passes through (spec 02),
 * and the keys that tie an account to its office and a requester to their college.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code', 20)->unique();
            $table->string('type', 20)->index();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('college_id')->references('id')->on('departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['college_id']);
        });

        Schema::dropIfExists('departments');
    }
};
