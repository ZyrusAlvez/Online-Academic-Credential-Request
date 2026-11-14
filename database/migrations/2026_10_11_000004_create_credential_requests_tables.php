<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A credential request and everything recorded about it (specs 04, 05, 11, 15, 17): the ID it was
 * made with, what the requester still owes, the payment, its route through the offices, each
 * physical hand-off, delays, pauses, representatives, uploads, the release and the ledger.
 *
 * History is kept: these tables restrict deletes instead of cascading, and `request_events` is
 * append-only. The status columns get their foreign keys in the request statuses migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('id_type', 60);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credential_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // The request's own copy of the requester's details and ID (spec 15).
            $table->string('requester_name', 150)->default('');
            $table->string('contact_number', 20)->nullable();
            $table->foreignId('credential_type_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 500);
            $table->string('receiving_institution')->nullable();
            $table->foreignId('college_id')->constrained('departments')->restrictOnDelete();
            $table->string('academic_status', 20);
            $table->string('program', 150);
            $table->string('student_number', 30)->nullable();
            $table->unsignedSmallInteger('attended_from');
            $table->unsignedSmallInteger('attended_to');
            $table->foreignId('identity_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('identity_status', 20)->default('pending')->index();
            $table->foreignId('identity_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('identity_reviewed_at')->nullable();
            $table->string('identity_remarks', 500)->nullable();
            $table->string('requester_mode', 20)->default('personal');
            $table->boolean('requires_payment')->default(false);
            $table->decimal('fee', 10, 2)->nullable();
            $table->decimal('amount_due', 10, 2)->nullable();
            $table->string('status', 30);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluation_started_at')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('requirements_completed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('adjusted_due_at')->nullable();
            $table->timestamp('effective_due_at')->nullable()->storedAs('coalesce(`adjusted_due_at`, `due_at`)');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedSmallInteger('processing_working_days')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_due_at']);
            $table->index(['status', 'submitted_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('accepted_at');
            $table->index('released_at');
            $table->index(['evaluated_by', 'evaluation_started_at']);
        });

        Schema::create('request_deficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();
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
            $table->date('paid_on')->nullable();
            $table->string('status', 20)->index();
            $table->string('official_receipt_no', 50)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('task', 20);
            $table->string('instructions')->nullable();
            $table->string('status', 20);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['credential_request_id', 'position']);
        });

        Schema::create('office_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_step_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('visit_no');
            $table->string('purpose', 20);
            $table->string('status', 20);
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->timestamp('forwarded_at');
            $table->foreignId('forwarded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_to_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('return_reason', 500)->nullable();
            $table->string('corrective_action', 500)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('working_days')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['department_id', 'completed_at']);
            $table->index(['credential_request_id', 'id']);
        });

        Schema::create('delay_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category', 30)->index();
            $table->string('reason', 500);
            $table->string('corrective_action', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('due_date_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->string('reason', 500);
            $table->string('resume_status', 30);
            $table->timestamp('started_at');
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('working_days')->nullable();
            $table->timestamps();
        });

        Schema::create('request_representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('relationship', 100)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('status', 20);
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_representative_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            // The documentary requirement an optional copy was uploaded for (REQ-04-18).
            $table->text('requirement')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('recipient_type', 20);
            $table->foreignId('request_representative_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('recipient_name', 150);
            $table->string('id_presented', 100)->nullable();
            $table->boolean('acknowledged');
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at');
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('request_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('office_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['credential_request_id', 'id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['request_events', 'releases', 'request_documents', 'request_representatives', 'due_date_pauses',
            'delay_notes', 'office_visits', 'request_steps', 'payment_proofs', 'request_deficiencies',
            'credential_requests', 'identity_documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
