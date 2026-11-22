<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use App\Models\User;
use App\Notifications\DueReminder;
use App\Services\WorkingCalendar;
use App\Support\OfficeStaff;
use App\Support\RequestRegistrars;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Daily reminders about due and overdue requests (spec 06, REQ-06-11..14). Every reminder is
 * logged first, so running the command again sends nothing new.
 */
class SendDueReminders extends Command
{
    protected $signature = 'app:send-due-reminders';

    protected $description = 'Remind offices of documents due soon or overdue, and tell requesters about overdue requests';

    public function handle(WorkingCalendar $calendar): int
    {
        if (! $calendar->isWorkingDay(now())) {
            $this->info('Not a working day; no reminders sent.');

            return self::SUCCESS;
        }

        $sent = 0;

        // Offices with their own deadline are reminded by it (REQ-30-08).
        OfficeVisit::query()
            ->where('status', VisitStatus::Received)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $calendar->addWorkingDays(now(), 1))
            ->whereHas('credentialRequest', fn (Builder $query) => $query->whereIn('status', RequestStatus::processing()))
            ->with(['department', 'credentialRequest.credentialType'])
            ->chunkById(100, function (Collection $visits) use (&$sent): void {
                foreach ($visits as $visit) {
                    /** @var OfficeVisit $visit */
                    $sent += $this->remindOffice($visit->credentialRequest, $visit, $visit->isOverdue());
                }
            });

        CredentialRequest::query()
            ->whereIn('status', RequestStatus::processing())
            ->where('effective_due_at', '<=', $calendar->addWorkingDays(now(), 1))
            ->with(['openVisit.department', 'openVisit.fromDepartment', 'student', 'credentialType'])
            ->chunkById(100, function (Collection $requests) use (&$sent): void {
                foreach ($requests as $request) {
                    /** @var CredentialRequest $request */
                    $sent += $this->remind($request);
                }
            });

        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }

    private function remind(CredentialRequest $request): int
    {
        $overdue = $request->effective_due_at?->isPast() === true;
        $sent = 0;
        $visit = $request->openVisit;

        // The holding office's department staff, unless its own deadline applies (above);
        // registrars hear about every request below.
        if ($visit !== null && $visit->due_at === null) {
            $sent += $this->remindOffice($request, $visit, $overdue);
        }

        // The request's registrar, once per request for each kind, wherever the paper is (REQ-06-20, 21).
        if ($this->firstTime($overdue ? 'registrar_overdue' : 'registrar_due_soon', $request->id, $request)) {
            $registrars = RequestRegistrars::for($request);

            if ($registrars->isNotEmpty()) {
                Notification::send($registrars, new DueReminder($request, $overdue));
                $sent++;
            }
        }

        // A walk-in request with no account yet has no one to remind (REQ-26-09).
        $owner = $request->student;

        if ($overdue && $owner?->is_active && $this->firstTime('requester_overdue', $request->id, $request)) {
            $owner->notify(new DueReminder($request, true));
            $sent++;
        }

        return $sent;
    }

    /**
     * The staff of the office holding the paper, once per visit for each kind; by the office's own
     * deadline when the visit has one (REQ-30-08).
     */
    private function remindOffice(CredentialRequest $request, OfficeVisit $visit, bool $overdue): int
    {
        if (! $this->firstTime($overdue ? 'office_overdue' : 'office_due_soon', $visit->id, $request)) {
            return 0;
        }

        $staff = OfficeStaff::of($visit->department)->reject(fn (User $user): bool => $user->hasRole(Role::Registrar));

        if ($staff->isEmpty()) {
            return 0;
        }

        Notification::send($staff, new DueReminder($request, $overdue, $visit->due_at));

        return 1;
    }

    private function firstTime(string $kind, int $subjectId, CredentialRequest $request): bool
    {
        return DB::table('reminder_logs')->insertOrIgnore([
            'kind' => $kind,
            'subject_id' => $subjectId,
            'credential_request_id' => $request->id,
            'created_at' => now(),
        ]) === 1;
    }
}
