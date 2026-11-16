<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several credentials in one application (spec 27). Every request made so far becomes an
 * application of one credential, with the same tracking number and id (REQ-27-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 20)->default('online');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable()->after('id');
            $table->unsignedSmallInteger('item_no')->default(1)->after('application_id');
        });

        DB::statement(<<<'SQL'
            INSERT INTO request_applications (id, reference_no, user_id, channel, submitted_at, created_at, updated_at)
            SELECT id, COALESCE(reference_no, CONCAT('legacy-', id)), user_id, channel, submitted_at, created_at, updated_at
            FROM credential_requests
            SQL);
        DB::table('credential_requests')->update(['application_id' => DB::raw('id')]);

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable(false)->change();
            $table->foreign('application_id')->references('id')->on('request_applications')->restrictOnDelete();
            $table->unique(['application_id', 'item_no']);
        });
    }

    public function down(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropUnique(['application_id', 'item_no']);
            $table->dropColumn(['application_id', 'item_no']);
        });

        Schema::dropIfExists('request_applications');
    }
};
