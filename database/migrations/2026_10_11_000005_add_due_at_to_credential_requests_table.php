<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('routed_at');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_at']);
            $table->dropColumn('due_at');
        });
    }
};
