<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The final quality check no longer has checklist parts (REQ-16-19): delete the results saved for
 * them, which no `CheckItem` case reads any more. Each final check stays in the ledger
 * (`StepCompleted`, with the registrar, time and remarks).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('request_checks')->whereIn('item', ['credential_document', 'signatures'])->delete();
    }

    /**
     * What was deleted can't be brought back.
     */
    public function down(): void
    {
        //
    }
};
