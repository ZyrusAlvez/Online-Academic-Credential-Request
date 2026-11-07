<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an office's task was done, separately from when the paper left it (REQ-17-06, 07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_visits', function (Blueprint $table) {
            $table->timestamp('done_at')->nullable()->after('received_by');
            $table->foreignId('done_by')->nullable()->after('done_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('done_by');
            $table->dropColumn('done_at');
        });
    }
};
