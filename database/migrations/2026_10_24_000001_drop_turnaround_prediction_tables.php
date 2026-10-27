<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Turnaround prediction (former spec 08) was removed. Servers that ran it still have its
 * predictions table and the registrar API tokens table; fresh databases never create them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('turnaround_predictions');
        Schema::dropIfExists('personal_access_tokens');
    }

    /**
     * The feature is gone, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
