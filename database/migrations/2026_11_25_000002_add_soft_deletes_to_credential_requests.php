<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 37: a request the Admin deletes is kept and hidden (soft delete), with who and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->softDeletes()->index();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->restrictOnDelete();
            $table->string('deletion_reason', 500)->nullable()->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deletion_reason');
            $table->dropSoftDeletes();
        });
    }
};
