<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Notifications\DueReminder;
use App\Services\WorkingCalendar;
use App\Support\OfficeStaff;
use Illuminate\Console\Command;
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

        CredentialRequest::query()
            ->whereIn('status', RequestStatus::processing())
            ->where('effective_due_at', '<=', $calendar->addWorkingDays(now(), 1))
            ->with(['openVisit.department', 'student', 'credentialType'])
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

        if ($visit !== null && $this->firstTime($overdue ? 'office_overdue' : 'office_due_soon', $visit->id, $request)) {
            Notification::send(OfficeStaff::of($visit->department), new DueReminder($request, $overdue));
            $sent++;
        }

        if ($overdue && $request->student->is_active && $this->firstTime('requester_overdue', $request->id, $request)) {
            $request->student->notify(new DueReminder($request, true));
            $sent++;
        }

        return $sent;
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
