<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The status of a request returned for clarification (REQ-20-01), third in the list, and the
 * narrower meaning of pending compliance. No request or ledger row changes.
 */
return new class extends Migration
{
    private const ORDER = [
        'submitted', 'under_evaluation', 'returned_for_clarification', 'pending_compliance',
        'returned_for_customer_action', 'awaiting_payment', 'payment_verification', 'accepted_for_processing',
        'in_progress', 'returned_for_correction', 'for_final_approval', 'ready_for_release',
        'released', 'declined', 'cancelled',
    ];

    private const PENDING_BEFORE = 'Returned to the requester: they must fix what was rejected or comply in person.';

    private const PENDING_AFTER = 'The requester must bring originals or complete clearance at the Office of the Registrar.';

    public function up(): void
    {
        // Out of the way of the unique order first.
        DB::table('request_statuses')->update(['sort_order' => DB::raw('sort_order + 100')]);

        DB::table('request_statuses')->insert([
            'value' => 'returned_for_clarification',
            'label' => 'Returned for clarification',
            'meaning' => 'Returned to the requester to correct the request online and resubmit it.',
            'sort_order' => 0,
            'is_final' => false,
        ]);

        $this->renumber(self::ORDER);

        DB::table('request_statuses')->where('value', 'pending_compliance')->update(['meaning' => self::PENDING_AFTER]);
    }

    public function down(): void
    {
        DB::table('request_statuses')->where('value', 'returned_for_clarification')->delete();
        DB::table('request_statuses')->update(['sort_order' => DB::raw('sort_order + 100')]);
        $this->renumber(array_values(array_diff(self::ORDER, ['returned_for_clarification'])));

        DB::table('request_statuses')->where('value', 'pending_compliance')->update(['meaning' => self::PENDING_BEFORE]);
    }

    /**
     * @param  list<string>  $order
     */
    private function renumber(array $order): void
    {
        foreach ($order as $index => $value) {
            DB::table('request_statuses')->where('value', $value)->update(['sort_order' => $index + 1]);
        }
    }
};
