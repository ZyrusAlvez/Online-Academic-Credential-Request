<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credential master list and each type's route through the offices (spec 03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_payment')->default(false);
            $table->decimal('fee', 10, 2)->nullable();
            $table->unsignedTinyInteger('processing_days')->default(5);
            $table->json('requirements')->nullable();
            $table->text('special_conditions')->nullable();
            $table->boolean('requires_receiving_institution')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // Empty when the step belongs to the requester's own college (the Dean).
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_student_college')->default(false);
            $table->string('task', 20)->default('sign');
            $table->string('instructions')->nullable();
            $table->timestamps();

            $table->index(['credential_type_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('credential_types');
    }
};
