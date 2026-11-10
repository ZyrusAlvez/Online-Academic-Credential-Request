<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every request status in one table, and every status column held to it (REQ-19-01, 03, 04).
 * The rows are literal values, not read from App\Enums\RequestStatus, so this migration means the
 * same thing later; RequestStatusTableTest keeps the two in step (REQ-19-02).
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string}> value, label, meaning */
    private const STATUSES = [
        ['submitted', 'Submitted', 'Online request received.'],
        ['under_evaluation', 'Under evaluation', 'Academic records and requirements are being reviewed.'],
        ['pending_compliance', 'Pending compliance', 'Returned to the requester: they must fix what was rejected or comply in person.'],
        ['returned_for_customer_action', 'Returned for customer action', 'No longer used (spec 16); kept for older requests.'],
        ['awaiting_payment', 'Awaiting payment', 'Requirements complete; payment instructions provided.'],
        ['payment_verification', 'Payment verification', 'Proof of payment is awaiting verification.'],
        ['accepted_for_processing', 'Accepted for processing', 'All conditions satisfied; the processing period has started.'],
        ['in_progress', 'In progress', 'The credential is being prepared or processed by the required offices.'],
        ['returned_for_correction', 'Returned for correction', 'An office returned the document to an earlier office for correction.'],
        ['for_final_approval', 'For final approval', 'Back at the Registrar for the final quality check.'],
        ['ready_for_release', 'Ready for release', 'Processing complete; available for claiming.'],
        ['released', 'Released', 'Released to the owner or an authorized representative.'],
        ['declined', 'Declined', 'Declined by the Office of the Registrar at evaluation, payment validation or the final quality check; reason recorded.'],
        ['cancelled', 'Cancelled', 'Cancelled by the requester before acceptance.'],
    ];

    private const FINAL = ['released', 'declined', 'cancelled'];

    /** @var array<string, list<string>> table => status columns */
    private const COLUMNS = [
        'credential_requests' => ['status'],
        'request_events' => ['from_status', 'to_status'],
        'due_date_pauses' => ['resume_status'],
    ];

    public function up(): void
    {
        Schema::create('request_statuses', function (Blueprint $table) {
            $table->string('value', 30)->primary();
            $table->string('label', 60);
            $table->string('meaning', 255);
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->boolean('is_final');
        });

        DB::table('request_statuses')->insert(array_map(fn (array $status, int $index): array => [
            'value' => $status[0],
            'label' => $status[1],
            'meaning' => $status[2],
            'sort_order' => $index + 1,
            'is_final' => in_array($status[0], self::FINAL, true),
        ], self::STATUSES, array_keys(self::STATUSES)));

        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->foreign($column)->references('value')->on('request_statuses')
                        ->restrictOnUpdate()->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }

        Schema::dropIfExists('request_statuses');
    }
};
