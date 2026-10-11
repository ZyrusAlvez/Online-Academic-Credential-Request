<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('credential_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('college_id')->constrained('departments')->restrictOnDelete();
            $table->string('student_number', 30);
            $table->string('program', 150);
            $table->string('student_status', 10);
            $table->string('last_school_year', 9);
            $table->string('purpose', 500);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->boolean('requires_payment');
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('status', 30);
            $table->timestamp('routed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('instructions')->nullable();
            $table->string('status', 20);
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->index(['credential_request_id', 'position']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->string('reference_no', 100)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('request_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['credential_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('request_steps');
        Schema::dropIfExists('credential_requests');
    }
};
