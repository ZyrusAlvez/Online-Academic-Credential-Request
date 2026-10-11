<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnaround_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('model_version', 40);
            $table->decimal('predicted_days', 6, 2);
            $table->timestamp('predicted_ready_at');
            $table->timestamp('due_at');
            $table->boolean('at_risk')->index();
            $table->json('features');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnaround_predictions');
    }
};
