<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documentary requirement a requester's file is a copy of, as the credential listed it at
 * submission (REQ-04-19). Empty for a representative's SPA and ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            // Text: the master list allows a long requirement line.
            $table->text('requirement')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropColumn('requirement');
        });
    }
};
