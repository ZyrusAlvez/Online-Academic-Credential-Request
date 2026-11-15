<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documentary requirements are submitted in person, so their uploaded copies go (REQ-04-22):
 * the rows first, since their kind no longer exists, then the column that named the requirement.
 * The stored files are left to the environment's reset, which empties their folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('request_documents')->where('kind', 'requirement')->delete();

        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropColumn('requirement');
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->text('requirement')->nullable()->after('kind');
        });
    }
};
