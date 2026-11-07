<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The valid ID is checked on each request, which keeps its own copy of the requester's name,
 * contact number and ID; the account no longer has an identity status (spec 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->string('requester_name', 150)->default('')->after('user_id');
            $table->string('contact_number', 20)->nullable()->after('requester_name');
            $table->foreignId('identity_document_id')->nullable()->after('attended_to')->constrained()->restrictOnDelete();
            $table->string('identity_status', 20)->default('pending')->index()->after('identity_document_id');
            $table->foreignId('identity_reviewed_by')->nullable()->after('identity_status')->constrained('users')->restrictOnDelete();
            $table->timestamp('identity_reviewed_at')->nullable()->after('identity_reviewed_by');
            $table->string('identity_remarks', 500)->nullable()->after('identity_reviewed_at');
        });

        $this->copyOwnersOntoRequests();

        Schema::table('identity_documents', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'remarks']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['identity_status']);
            $table->dropColumn(['identity_status', 'identity_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_status', 20)->default('unsubmitted')->index()->after('attended_to');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_status');
        });

        Schema::table('identity_documents', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->index()->after('size');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('remarks', 500)->nullable()->after('reviewed_at');
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_reviewed_by');
            $table->dropConstrainedForeignId('identity_document_id');
            $table->dropIndex(['identity_status']);
            $table->dropColumn(['requester_name', 'contact_number', 'identity_status', 'identity_reviewed_at', 'identity_remarks']);
        });
    }

    /**
     * Each existing request gets its owner's current name, contact number and latest ID, with
     * that ID's review (REQ-15-14).
     */
    private function copyOwnersOntoRequests(): void
    {
        $owners = DB::table('users')
            ->whereIn('id', DB::table('credential_requests')->select('user_id'))
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'email', 'contact_number']);

        foreach ($owners as $owner) {
            $name = trim(implode(' ', array_filter([$owner->first_name, $owner->middle_name, $owner->last_name], filled(...))));

            $id = DB::table('identity_documents')->where('user_id', $owner->id)->orderByDesc('id')
                ->first(['id', 'status', 'reviewed_by', 'reviewed_at', 'remarks']);

            DB::table('credential_requests')->where('user_id', $owner->id)->update([
                'requester_name' => $name !== '' ? $name : $owner->email,
                'contact_number' => $owner->contact_number,
                'identity_document_id' => $id?->id,
                'identity_status' => $id === null ? 'pending' : $id->status,
                'identity_reviewed_by' => $id?->reviewed_by,
                'identity_reviewed_at' => $id?->reviewed_at,
                'identity_remarks' => $id?->remarks,
            ]);
        }
    }
};
