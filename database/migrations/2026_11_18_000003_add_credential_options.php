<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type options the Admin sets (spec 29): asking for the terms and academic year, a fee per
 * term and the academic statuses a credential is free for; and each request's terms and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_types', function (Blueprint $table) {
            $table->boolean('asks_term_and_year')->default(false)->after('requires_receiving_institution');
            $table->boolean('fee_per_term')->default(false)->after('fee');
            $table->json('free_for')->nullable()->after('fee_per_term');
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->json('terms')->nullable()->after('receiving_institution');
            $table->string('academic_year', 9)->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropColumn(['terms', 'academic_year']);
        });

        Schema::table('credential_types', function (Blueprint $table) {
            $table->dropColumn(['asks_term_and_year', 'fee_per_term', 'free_for']);
        });
    }
};
