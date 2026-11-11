<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Details verified once (spec 21): the request on which each part of a requester's details was
 * verified, and the checklist parts confirmed from it on later requests.
 */
return new class extends Migration
{
    private const PARTS = ['identity', 'academic_record'];

    public function up(): void
    {
        Schema::create('requester_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('part', 30);
            $table->foreignId('credential_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');

            $table->unique(['user_id', 'part']);
        });

        Schema::table('request_checks', function (Blueprint $table) {
            $table->foreignId('carried_from_request_id')->nullable()->after('checked_at')
                ->constrained('credential_requests')->nullOnDelete();
        });

        // Each requester's latest request whose evaluation was completed (REQ-21-07).
        $latest = DB::table('credential_requests')
            ->whereNotNull('requirements_completed_at')
            ->whereNotNull('identity_reviewed_by')
            ->groupBy('user_id')
            ->selectRaw('max(id) as id')
            ->pluck('id');

        DB::table('credential_requests')->whereIn('id', $latest)->orderBy('id')
            ->each(function (stdClass $request): void {
                foreach (self::PARTS as $part) {
                    DB::table('requester_verifications')->insert([
                        'user_id' => $request->user_id,
                        'part' => $part,
                        'credential_request_id' => $request->id,
                        'verified_by' => $request->identity_reviewed_by,
                        'verified_at' => $request->identity_reviewed_at ?? $request->requirements_completed_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('request_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carried_from_request_id');
        });

        Schema::dropIfExists('requester_verifications');
    }
};
