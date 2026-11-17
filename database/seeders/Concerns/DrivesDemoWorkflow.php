<?php

namespace Database\Seeders\Concerns;

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RecipientType;
use App\Enums\RouteTask;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * What the demo request seeders share (spec 31): the actors, driving a request through the real
 * RequestWorkflow, and a clock that moves through the past without passing the moment the seeding
 * started (REQ-31-05).
 */
trait DrivesDemoWorkflow
{
    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private RequestWorkflow $workflow;

    /** The registrar acting now: the evaluator, and the Registrar's office steps. */
    private User $registrar;

    private User $cashier;

    /** @var Collection<string, User> office code => staff account */
    private Collection $staff;

    /** The real moment the seeding started; the demo clock never passes it. */
    private Carbon $realNow;

    private function bootDemo(RequestWorkflow $workflow): void
    {
        if (! app()->environment(['local', 'staging']) && ! app()->runningUnitTests()) {
            throw new RuntimeException('The demo seeders only run in the local and staging environments.');
        }

        $this->workflow = $workflow;
        $this->registrar = DemoSeeder::registrarAccount();
        $this->cashier = DemoSeeder::cashierAccount();
        $this->staff = Department::active()->get()->keyBy('code')->map(fn (Department $department) => DemoSeeder::staffAccount($department));
        $this->realNow = Carbon::now();
    }

    /**
     * @param  ?list<string>  $terms
     */
    private function submit(User $owner, CredentialType $type, string $purpose, ?string $receivingSchool = null, ?array $terms = null, ?string $academicYear = null): CredentialRequest
    {
        return $this->workflow->submit($owner, [
            'credential_type_id' => $type->id,
            'purpose' => $purpose,
            'receiving_institution' => $receivingSchool,
            'terms' => $terms,
            'academic_year' => $academicYear,
        ]);
    }

    private function evaluate(CredentialRequest $request): void
    {
        $this->later(2);
        $this->workflow->startEvaluation($request, $this->registrar);
        $this->later(1);
        $this->check($request, CheckStage::Evaluation);
        $this->later(2);
        $this->workflow->approveEvaluation($request, $this->registrar, 'Records and requirements complete.');
    }

    /**
     * Confirm every part of a stage's checklist, or all but the one rejected (spec 16).
     */
    private function check(CredentialRequest $request, CheckStage $stage, ?CheckItem $reject = null): void
    {
        foreach (CheckItem::for($stage, $request->fresh() ?? $request) as $item) {
            $this->workflow->recordCheck($request, $this->registrar, $item, $item === $reject ? CheckResult::Rejected : CheckResult::Confirmed);
        }
    }

    private function pay(CredentialRequest $request): void
    {
        $this->later(5);
        $this->workflow->submitPaymentProof($request, $request->student()->firstOrFail(), $this->image('receipt.png'),
            'UPHSL-'.fake()->numerify('########'), (string) $request->amount_due, now()->format('Y-m-d'));
    }

    /**
     * Paid (when there is anything to pay) and accepted by the cashier.
     */
    private function payAndAccept(CredentialRequest $request): void
    {
        // A free credential is accepted when its evaluation is complete.
        if ($request->fresh()?->requires_payment) {
            $this->pay($request);
            $this->later(4);
            // Online payments are the cashier's to validate (REQ-25-04).
            $this->workflow->verifyPaymentAndAccept($request, $this->cashier);
        }
    }

    private function accepted(User $owner, CredentialType $type, string $purpose, ?string $receivingSchool = null): CredentialRequest
    {
        $request = $this->submit($owner, $type, $purpose, $receivingSchool);
        $this->evaluate($request);
        $this->payAndAccept($request);

        return $request;
    }

    /**
     * Receive and complete visits in order until the document reaches $stopAt (and is received
     * there when $receive), or until it is ready for release when $stopAt is null. $slowAt holds the
     * document at that office for $slowHours before its task is done.
     */
    private function advanceUntil(CredentialRequest $request, ?string $stopAt, bool $receive = false, bool $finalCheck = false, ?string $slowAt = null, int $slowHours = 0): void
    {
        while ($visit = $request->openVisit()->with(['department', 'step'])->first()) {
            $code = $visit->department->code;
            $isTarget = $code === $stopAt && (! $finalCheck || $visit->step->task === RouteTask::FinalCheck);

            if ($isTarget && ! $receive) {
                return;
            }

            if ($visit->status === VisitStatus::InTransit) {
                $this->later(fake()->numberBetween(2, 20));
                $this->workflow->acceptReceipt($visit, $this->actorFor($code));
            }

            if ($isTarget) {
                return;
            }

            $this->later($code === $slowAt ? $slowHours : fake()->numberBetween(3, 30));

            $this->workflow->completeVisit($visit, $this->actorFor($code), fake()->optional(0.3)->randomElement(['Signed.', 'Verified.', 'Stamped.']));

            // Done, then the office sends the paper on a little later (REQ-17-07).
            if ($visit->status === VisitStatus::Done) {
                $this->later(fake()->numberBetween(1, 6));
                $this->workflow->forwardDocument($visit, $this->actorFor($code));
            }
        }
    }

    private function release(CredentialRequest $request, RecipientType $to = RecipientType::Owner, ?int $representativeId = null): void
    {
        $this->workflow->release($request, $this->registrar, $to, $representativeId);
    }

    /**
     * A placeholder PNG upload. Built from bytes rather than UploadedFile::fake()->image(), which
     * needs the GD extension that the servers do not have (the app itself never draws images).
     */
    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) base64_decode(self::PNG));
    }

    private function actorFor(string $code): User
    {
        return $code === Department::REGISTRAR_CODE ? $this->registrar : $this->staff[$code];
    }

    /**
     * Move the clock forward by some hours (office actions take time), never past the moment the
     * seeding started (REQ-31-05).
     */
    private function later(int $hours): void
    {
        $next = Carbon::now()->addHours($hours);
        $limit = $this->realNow->copy()->subMinutes(1);

        Carbon::setTestNow($next->greaterThan($limit) ? max(Carbon::now(), $limit) : $next);
    }

    /**
     * Run $callback as if it started $daysAgo days ago (ledger times follow).
     */
    private function at(int $daysAgo, callable $callback): void
    {
        $start = $this->realNow->copy()->subDays($daysAgo)->setTime(fake()->numberBetween(8, 10), fake()->numberBetween(0, 59));
        // Today before office hours: start a little before now instead of later today.
        Carbon::setTestNow($start->greaterThan($this->realNow) ? $this->realNow->copy()->subHours(3) : $start);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
