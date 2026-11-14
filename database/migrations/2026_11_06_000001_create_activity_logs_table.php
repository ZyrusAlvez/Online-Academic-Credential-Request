<?php

use App\Actions\Audit\RecordRequestEvent;
use App\Models\RequestEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The system-wide audit log (spec 14). Deploys only run migrations, so the request actions already
 * in the ledgers are copied in here (REQ-14-14); tests build their own data and skip the copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 20)->nullable();
            $table->string('category', 20);
            $table->string('action', 60);
            $table->string('description', 500);
            $table->string('reference_no', 20)->nullable()->index();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['actor_id', 'created_at']);
            $table->index(['category', 'created_at']);
        });

        if (app()->runningUnitTests()) {
            return;
        }

        $record = app(RecordRequestEvent::class);

        RequestEvent::with(['credentialRequest', 'actor', 'department', 'step'])
            ->chunkById(500, function ($events) use ($record): void {
                foreach ($events as $event) {
                    $record->handle($event, $event->credentialRequest, $event->actor, at: $event->created_at);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
