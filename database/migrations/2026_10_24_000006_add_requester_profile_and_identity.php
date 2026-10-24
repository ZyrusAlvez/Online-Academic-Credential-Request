<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requester profile, privacy consent and valid-ID verification (spec 01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->after('email');
            $table->timestamp('privacy_consented_at')->nullable()->after('contact_number');
            $table->string('academic_status', 20)->nullable()->after('department_id');
            $table->foreignId('college_id')->nullable()->after('academic_status')->constrained('departments')->restrictOnDelete();
            $table->string('program', 150)->nullable()->after('college_id');
            $table->string('student_number', 30)->nullable()->after('program');
            $table->unsignedSmallInteger('attended_from')->nullable()->after('student_number');
            $table->unsignedSmallInteger('attended_to')->nullable()->after('attended_from');
            $table->string('identity_status', 20)->default('unsubmitted')->index()->after('attended_to');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_status');
        });

        Schema::create('identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('id_type', 60);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->string('status', 20)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_documents');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
            $table->dropIndex(['identity_status']);
            $table->dropColumn([
                'contact_number', 'privacy_consented_at', 'academic_status', 'program', 'student_number',
                'attended_from', 'attended_to', 'identity_status', 'identity_verified_at',
            ]);
        });
    }
};
