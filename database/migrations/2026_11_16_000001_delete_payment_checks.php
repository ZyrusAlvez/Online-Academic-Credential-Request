<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payment Validation has no checklist any more (REQ-16-17, 2026-11-16): delete the results saved
 * for its two parts, so `CheckItem` never meets a value it no longer has. The decisions stay in the
 * ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('request_checks')->whereIn('item', ['payment_proof', 'payment_amount'])->delete();
    }

    public function down(): void
    {
        // The rows cannot be restored, and nothing reads them.
    }
};
