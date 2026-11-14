<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts of every role. A requester's row also holds their saved profile, which prefills the
 * next request (spec 15). The two department columns get their foreign keys in the departments
 * migration, because that table is created after this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Sign-up asks only for the email; the name comes with the requester's details.
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email')->unique();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('contact_number', 20)->nullable();
            $table->timestamp('privacy_consented_at')->nullable();
            $table->string('role', 20)->default('student')->index();
            // Department staff: the office they work in.
            $table->foreignId('department_id')->nullable();
            // Requesters: the saved academic profile.
            $table->string('academic_status', 20)->nullable();
            $table->foreignId('college_id')->nullable();
            $table->string('program', 150)->nullable();
            $table->string('student_number', 30)->nullable();
            $table->unsignedSmallInteger('attended_from')->nullable();
            $table->unsignedSmallInteger('attended_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            // Empty for an account that only signs in with Google.
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
