<?php

use App\Models\CredentialType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Office deadlines (spec 30): each route step's share of the processing period, copied onto a
 * request's route at acceptance, and each office visit's deadline. Existing routes get the
 * automatic split (REQ-30-02); requests already accepted keep no office deadlines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('allotted_days')->default(0)->after('task');
        });

        Schema::table('request_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('allotted_days')->nullable()->after('task');
        });

        Schema::table('office_visits', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('received_by');
        });

        foreach (DB::table('credential_types')->pluck('processing_days', 'id') as $typeId => $days) {
            $steps = DB::table('workflow_steps')->where('credential_type_id', $typeId)->orderBy('position')->pluck('id');

            foreach (CredentialType::splitDays((int) $days, $steps->count()) as $index => $allotted) {
                DB::table('workflow_steps')->where('id', $steps[$index])->update(['allotted_days' => $allotted]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('office_visits', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });

        Schema::table('request_steps', function (Blueprint $table) {
            $table->dropColumn('allotted_days');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('allotted_days');
        });
    }
};
