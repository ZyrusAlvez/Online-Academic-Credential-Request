<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the College of Law together with everything that names it (REQ-02-18). The client's
 * 2026-11-06 list has no College of Law, so nothing of it is kept, not even as an inactive row.
 * Only that code: a college the registrar adds later is theirs to keep.
 *
 *
 *  - requests filed under it, or routed through it, are deleted with their steps, visits, ledger,
 *    uploads and notifications (activity log entries about them keep their reference number);
 *  - requesters who named it as their college have their college and program cleared, and choose
 *    again on their next request;
 *  - its staff accounts are deleted, its programs and route steps removed, and activity log entries
 *    lose their link to it (they keep their copied names and description).
 *
 * This is the one deliberate exception to the append-only request_events and activity_logs rule,
 * so it goes through the query builder. Safe to run again: with no such college it does nothing.
 */
class RemoveCollegeOfLawSeeder extends Seeder
{
    public const CODE = 'LAW';

    /** Tables that hold a credential request's rows, children before parents. */
    private const REQUEST_TABLES = [
        'request_events', 'delay_notes', 'releases', 'request_documents', 'office_visits', 'request_steps',
        'payment_proofs', 'request_checks', 'request_deficiencies', 'due_date_pauses', 'request_representatives', 'reminder_logs',
    ];

    public function run(): void
    {
        $colleges = Department::where('type', DepartmentType::College)->where('code', self::CODE)->pluck('id');

        if ($colleges->isEmpty()) {
            return;
        }

        $files = DB::transaction(function () use ($colleges): Collection {
            $files = $this->deleteRequests($colleges);

            DB::table('users')->whereIn('college_id', $colleges)->update(['college_id' => null, 'program' => null]);
            $this->deleteStaff($colleges);

            DB::table('programs')->whereIn('department_id', $colleges)->delete();
            DB::table('workflow_steps')->whereIn('department_id', $colleges)->delete();
            DB::table('activity_logs')->whereIn('department_id', $colleges)->update(['department_id' => null]);
            DB::table('departments')->whereIn('id', $colleges)->delete();

            return $files;
        });

        // Only once the rows are gone: a rolled-back purge must not lose the uploads.
        if ($files->isNotEmpty()) {
            Storage::disk(RequestWorkflow::proofDisk())->delete($files->all());
        }
    }

    /**
     * Every request filed under one of the colleges or routed through it.
     *
     * @param  Collection<int, int>  $colleges
     * @return Collection<int, string> the paths of the deleted uploads
     */
    private function deleteRequests(Collection $colleges): Collection
    {
        $ids = DB::table('credential_requests')->whereIn('college_id', $colleges)->pluck('id')
            ->merge(DB::table('request_steps')->whereIn('department_id', $colleges)->pluck('credential_request_id'))
            ->merge(DB::table('office_visits')->where(fn ($q) => $q->whereIn('department_id', $colleges)
                ->orWhereIn('from_department_id', $colleges)
                ->orWhereIn('returned_to_department_id', $colleges))->pluck('credential_request_id'))
            ->merge(DB::table('request_events')->whereIn('department_id', $colleges)->pluck('credential_request_id'))
            ->merge(DB::table('delay_notes')->whereIn('department_id', $colleges)->pluck('credential_request_id'))
            ->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $files = DB::table('payment_proofs')->whereIn('credential_request_id', $ids)->pluck('path')
            ->merge(DB::table('request_documents')->whereIn('credential_request_id', $ids)->pluck('path'));

        foreach (self::REQUEST_TABLES as $table) {
            DB::table($table)->whereIn('credential_request_id', $ids)->delete();
        }

        DB::table('notifications')->whereIn('data->request_id', $ids)->delete();
        DB::table('credential_requests')->whereIn('id', $ids)->delete();

        return $files->filter()->values();
    }

    /**
     * The colleges' staff accounts. Their log entries keep the copied name; an account that still
     * acted elsewhere is kept, deactivated and detached, so that history stays.
     *
     * @param  Collection<int, int>  $colleges
     */
    private function deleteStaff(Collection $colleges): void
    {
        foreach (DB::table('users')->whereIn('department_id', $colleges)->pluck('id') as $id) {
            try {
                DB::transaction(function () use ($id): void {
                    DB::table('activity_logs')->where('actor_id', $id)->update(['actor_id' => null]);
                    DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())
                        ->where('notifiable_id', $id)->delete();
                    DB::table('sessions')->where('user_id', $id)->delete();
                    DB::table('users')->where('id', $id)->delete();
                });
            } catch (QueryException) {
                DB::table('users')->where('id', $id)->update(['department_id' => null, 'is_active' => false]);
            }
        }
    }
}
