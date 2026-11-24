<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 36: contact numbers are kept in E.164 form, so the 11-digit Philippine numbers saved so far
 * become `+639…` (REQ-36-02).
 */
return new class extends Migration
{
    /** Tables whose `contact_number` is the requester's (a representative's is unchanged). */
    private const TABLES = ['users', 'credential_requests'];

    public function up(): void
    {
        $this->convertNumbers();
    }

    /** `09171234567` → `+639171234567`; anything else is left as it is (REQ-36-02). */
    public function convertNumbers(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)
                ->whereRaw("contact_number REGEXP '^09[0-9]{9}$'")
                ->update(['contact_number' => DB::raw("CONCAT('+63', SUBSTRING(contact_number, 2))")]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)
                ->whereRaw("contact_number REGEXP '^\\\\+639[0-9]{9}$'")
                ->update(['contact_number' => DB::raw("CONCAT('0', SUBSTRING(contact_number, 4))")]);
        }
    }
};
