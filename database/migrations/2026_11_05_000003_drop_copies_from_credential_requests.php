<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One request is one document (REQ-04-01..03): the client's workflow has no number of copies.
 * Requests that have not reached payment yet owe the single fee; requests at payment verification
 * or later keep the amount that was paid.
 */
return new class extends Migration
{
    /** Statuses before a proof of payment is submitted. */
    private const UNPAID = ['submitted', 'under_evaluation', 'pending_compliance', 'returned_for_customer_action', 'awaiting_payment'];

    public function up(): void
    {
        DB::table('credential_requests')
            ->whereIn('status', self::UNPAID)
            ->whereNotNull('fee')
            ->where('copies', '>', 1)
            ->update(['amount_due' => DB::raw('fee')]);

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropColumn('copies');
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('copies')->default(1)->after('credential_type_id');
        });
    }
};
