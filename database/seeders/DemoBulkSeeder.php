<?php

namespace Database\Seeders;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckStage;
use App\Enums\RecipientType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Enums\Term;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\Purpose;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Services\WorkingCalendar;
use Database\Seeders\Concerns\DrivesDemoWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Months of generated activity for the dashboard, reports and queues (spec 31): extra requesters
 * and two more registrars, a coverage set that puts a request in every status and documents at
 * every office, then `uphsl.demo.applications` random applications over the past six months. All
 * through the real RequestWorkflow, without notifications. Run by DemoRequestSeeder, which seeds
 * Faker first so every run gives the same data (REQ-31-05).
 */
class DemoBulkSeeder extends Seeder
{
    use DrivesDemoWorkflow;

    private const WOMEN = ['Maria', 'Angelica', 'Kristine', 'Jasmine', 'Patricia', 'Camille', 'Andrea', 'Nicole', 'Bea', 'Joanna',
        'Rhea', 'Liza', 'Carmela', 'Danica', 'Erika', 'Frances', 'Hazel', 'Ivy', 'Janelle', 'Katrina', 'Leah', 'Mylene', 'Precious', 'Shiela'];

    private const MEN = ['Jose', 'Mark', 'John Paul', 'Christian', 'Paolo', 'Miguel', 'Rafael', 'Carlo', 'Joshua', 'Kenneth',
        'Adrian', 'Bryan', 'Daniel', 'Emmanuel', 'Francis', 'Gabriel', 'Jerome', 'Lorenzo', 'Nathaniel', 'Renz', 'Ramon', 'Vincent'];

    private const SURNAMES = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada',
        'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro', 'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'Aguilar',
        'Dizon', 'Gonzales', 'Lopez', 'Manalo', 'Pascual', 'Soriano', 'Valdez', 'Del Rosario', 'De Guzman', 'Lim', 'Tan'];

    private const SCHOOLS = ['University of the Philippines Manila', 'Polytechnic University of the Philippines',
        'De La Salle University Dasmariñas', 'Far Eastern University', 'Mapúa University', 'University of Santo Tomas'];

    /** Credential name => weight; TOR is the most requested. */
    private const WEIGHTS = [
        'Transcript of Records (TOR)' => 30, 'Certification of Enrollment' => 10, 'Certification of Graduation' => 9,
        'Certificate of Grades' => 9, 'Semestral Report of Grades' => 8, 'Certification of Units Earned' => 5,
        'Course Description' => 5, 'Diploma / Certificate for Non-Degree Programs' => 5, 'Authentication' => 5,
        'Certification, Authentication, Verification (CAV)' => 3, 'Certificate of Transfer' => 3, 'TOR for Transfer Credentials' => 3,
        'Copy of Grades for Transfer Credentials' => 2, 'Medium of Instruction' => 2, 'Notice of Acceptance' => 1,
        'Letter for the BI Commissioner' => 1, 'Permit to Cross-Enroll' => 1,
    ];

    /** Outcomes before the credential is accepted, and those after. */
    private const BEFORE_ACCEPTANCE = ['submitted', 'evaluating', 'clarification', 'compliance', 'awaiting_payment', 'payment_check', 'cancelled'];

    /** @var list<User> */
    private array $requesters = [];

    /** @var array<string, list<User>> college code => its requesters */
    private array $byCollege = [];

    /** @var list<User> */
    private array $registrars = [];

    /** @var Collection<string, CredentialType> */
    private Collection $types;

    /** @var list<string> */
    private array $purposes = [];

    public function run(RequestWorkflow $workflow): void
    {
        $this->bootDemo($workflow);

        // The generated history notifies no one (REQ-31-05); the real notifier comes back after.
        $notifier = Notification::getFacadeRoot();
        Notification::fake();

        try {
            $this->registrars = [
                $this->registrar,
                DemoSeeder::registrarAccount('registrar2@example.com', 'Liza', 'Santos'),
                DemoSeeder::registrarAccount('registrar3@example.com', 'Ramon', 'Aquino'),
            ];
            $this->types = CredentialType::requestable()->get()->keyBy('name');
            $this->purposes = Purpose::active()->orderBy('name')->pluck('name')->all();

            $this->makeRequesters((int) config('uphsl.demo.requesters', 60));
            $this->coverage();

            for ($i = 0, $n = (int) config('uphsl.demo.applications', 300); $i < $n; $i++) {
                // Skewed to recent: more applications in the last weeks than months ago.
                $daysAgo = (int) floor(180 * (fake()->randomFloat(4, 0, 1) ** 1.7));
                $this->application($daysAgo, $this->outcomeFor($daysAgo), walkIn: fake()->boolean(10), several: fake()->boolean(12));
            }
        } finally {
            Notification::swap($notifier);
        }
    }

    // ── Accounts ─────────────────────────────────────────────────────────────────────────────

    /**
     * Requesters of every college (round-robin first), both sexes and every status, each with a
     * complete profile and a saved valid ID (REQ-31-02).
     */
    private function makeRequesters(int $count): void
    {
        $colleges = Department::colleges()->active()->orderBy('code')
            ->with(['programs' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->get()->filter(fn (Department $college): bool => $college->programs->isNotEmpty())->values();
        $password = Hash::make(DemoSeeder::PASSWORD);
        $count = max($count, $colleges->count());

        for ($i = 1; $i <= $count; $i++) {
            /** @var Department $college */
            $college = $i <= $colleges->count() ? $colleges[$i - 1] : fake()->randomElement($colleges->all());
            // The first ones cover both sexes and every academic status; the rest are random (REQ-31-02).
            $statuses = AcademicStatus::cases();
            $sex = match ($i) {
                1 => Sex::Female,
                2 => Sex::Male,
                default => fake()->boolean() ? Sex::Female : Sex::Male,
            };
            $first = fake()->randomElement($sex === Sex::Female ? self::WOMEN : self::MEN);
            $last = fake()->randomElement(self::SURNAMES);
            $status = $i <= count($statuses)
                ? $statuses[$i - 1]
                : fake()->randomElement([...array_fill(0, 14, AcademicStatus::Graduate), ...array_fill(0, 3, AcademicStatus::Current), ...array_fill(0, 3, AcademicStatus::Former)]);
            $to = $status === AcademicStatus::Current ? (int) $this->realNow->year : fake()->numberBetween(2008, (int) $this->realNow->year - 1);

            $user = User::firstOrNew(['email' => sprintf('%s.%s.%02d@example.com', Str::slug($first, ''), Str::slug($last, ''), $i)]);
            $user->fill([
                'first_name' => $first,
                'middle_name' => fake()->optional(0.7)->randomElement(self::SURNAMES),
                'last_name' => $last,
                'sex' => $sex,
                'password' => $password,
                'contact_number' => '+639'.fake()->numerify('#########'),
                'academic_status' => $status,
                'college_id' => $college->id,
                'program' => fake()->randomElement($college->programs->pluck('name')->all()),
                'student_number' => fake()->optional(0.8)->numerify(sprintf('%02d-####-###', $to % 100)),
                'attended_from' => $to - ($college->code === 'SHS' ? 1 : fake()->numberBetween(2, 4)),
                'attended_to' => $to,
            ]);
            $user->forceFill(['role' => Role::Student, 'is_active' => true, 'email_verified_at' => $this->realNow->copy()->subDays(200), 'privacy_consented_at' => $this->realNow->copy()->subDays(200)])->save();

            $this->at(fake()->numberBetween(185, 200), fn () => app(SubmitIdentityDocument::class)->handle($user, 'PhilSys National ID', $this->image('national-id.png')));

            $this->requesters[] = $user;
            $this->byCollege[$college->code][] = $user;
        }
    }

    // ── What to generate ─────────────────────────────────────────────────────────────────────

    /**
     * A request in every status the workflow reaches, documents received at and on their way to
     * every office, and some overdue (REQ-31-04).
     */
    private function coverage(): void
    {
        $ages = [
            'submitted' => [0, 2], 'evaluating' => [1, 3], 'clarification' => [2, 6], 'compliance' => [2, 8],
            'awaiting_payment' => [1, 5], 'payment_check' => [1, 4], 'accepted' => [1, 3], 'final_check' => [8, 14],
            'returned' => [6, 12], 'ready' => [12, 20], 'released' => [40, 90], 'declined' => [5, 30], 'cancelled' => [2, 10],
        ];

        $tor = $this->types['Transcript of Records (TOR)'] ?? null;
        $diploma = $this->types['Diploma / Certificate for Non-Degree Programs'] ?? null;

        if ($tor === null) {
            return;
        }

        // A paid credential with a long route, so every stage applies.
        foreach ($ages as $outcome => [$from, $to]) {
            foreach ([1, 2] as $_) {
                $this->application(fake()->numberBetween($from, $to), $outcome, items: [$this->item($tor)]);
            }
        }

        foreach (Department::active()->orderBy('code')->get() as $office) {
            $type = $office->code === 'PRES' ? $diploma : $tor;
            $owners = $this->byCollege[$office->code] ?? $this->requesters;

            if ($type === null || $owners === []) {
                continue;
            }

            foreach ([false, true, false, true] as $received) {
                $this->application(fake()->numberBetween(3, 9), 'in_progress', owner: fake()->randomElement($owners),
                    items: [$this->item($type)], stopAt: $office->code, receive: $received);
            }
        }

        // Overdue: accepted weeks ago and still at an office.
        foreach ([1, 2, 3] as $_) {
            $this->application(fake()->numberBetween(25, 40), 'in_progress', items: [$this->item($tor)], receive: true);
        }

        $this->deadlines($tor);

        // The day itself: new applications, and some already being evaluated (REQ-31-07).
        foreach (['submitted', 'submitted', 'submitted', 'evaluating', 'evaluating'] as $outcome) {
            $this->application(0, $outcome);
        }
    }

    /**
     * Credentials at an office whose due date is 5, 3 and 1 working days past, the as-of day, and
     * the next 1 and 2 working days, two of each (REQ-31-07).
     */
    private function deadlines(CredentialType $type): void
    {
        $calendar = app(WorkingCalendar::class);
        $today = $this->realNow->copy()->startOfDay();

        foreach ([-5, -3, -1, 0, 1, 2] as $offset) {
            $accepted = $this->acceptanceDayFor($calendar, $this->workingDayFrom($calendar, $today, $offset), $type->processing_days);

            // Accepted before the day itself, so nothing is dated after it (REQ-31-05).
            if ($accepted === null || ! $accepted->lt($today)) {
                continue;
            }

            foreach ([1, 2] as $_) {
                $this->acceptedOn((int) abs($accepted->diffInDays($today)), $type);
            }
        }
    }

    /**
     * The working day $offset working days from $today (0: today, or the next working day).
     */
    private function workingDayFrom(WorkingCalendar $calendar, Carbon $today, int $offset): Carbon
    {
        $day = $today->copy();
        $step = $offset < 0 ? -1 : 1;
        $left = abs($offset);

        if ($offset === 0) {
            while (! $calendar->isWorkingDay($day)) {
                $day->addDay();
            }

            return $day;
        }

        while ($left > 0) {
            $day->addDays($step);

            if ($calendar->isWorkingDay($day)) {
                $left--;
            }
        }

        return $day;
    }

    /**
     * The latest working day on which a credential of $days working days is accepted and comes
     * due on $due (REQ-10-02).
     */
    private function acceptanceDayFor(WorkingCalendar $calendar, Carbon $due, int $days): ?Carbon
    {
        for ($back = 1; $back <= 90; $back++) {
            $day = $due->copy()->subDays($back);

            if ($calendar->isWorkingDay($day) && $calendar->addWorkingDays($day, $days)->isSameDay($due)) {
                return $day;
            }
        }

        return null;
    }

    /**
     * Submitted two days before $daysAgo, evaluated the next day, then paid and accepted on that
     * day (so its due date is exact), and received at an office mid-route.
     */
    private function acceptedOn(int $daysAgo, CredentialType $type): void
    {
        $this->at($daysAgo + 2, function () use ($type): void {
            DB::transaction(function () use ($type): void {
                $this->registrar = fake()->randomElement($this->registrars);
                $owner = fake()->randomElement($this->requesters);
                $lead = $this->workflow->submit($owner, [$this->item($type)]);

                // Submitted 8–10 a.m.; evaluated the next day; paid and accepted around 1–3 p.m.
                // two days after submitting.
                $this->later(26);
                $this->workflow->startEvaluation($lead, $this->registrar);
                $this->check($lead, CheckStage::Evaluation);
                $this->workflow->approveEvaluation($lead, $this->registrar, 'Records and requirements complete.');
                $this->later(20);
                $this->pay($lead);
                $this->later(2);
                $this->workflow->verifyPaymentAndAccept($lead, $this->cashier);

                $codes = $lead->steps()->with('department')->orderBy('position')->get()->map(fn ($step) => (string) $step->department?->code)->all();
                $middle = array_values(array_filter(array_slice($codes, 1, -1), fn (string $code): bool => $code !== Department::REGISTRAR_CODE));

                if ($middle !== []) {
                    $this->advanceUntil($lead, (string) fake()->randomElement($middle), receive: true);
                }
            });
        });
    }

    /**
     * Older applications are mostly finished; recent ones are spread over the stages (REQ-31-03).
     */
    private function outcomeFor(int $daysAgo): string
    {
        $weights = match (true) {
            $daysAgo > 30 => ['released' => 80, 'declined' => 5, 'cancelled' => 5, 'ready' => 4, 'in_progress' => 6],
            $daysAgo >= 10 => ['released' => 40, 'ready' => 15, 'in_progress' => 22, 'final_check' => 5, 'returned' => 5,
                'declined' => 3, 'cancelled' => 3, 'compliance' => 4, 'awaiting_payment' => 3],
            default => ['submitted' => 18, 'evaluating' => 10, 'clarification' => 7, 'compliance' => 7, 'awaiting_payment' => 15,
                'payment_check' => 18, 'accepted' => 8, 'in_progress' => 18, 'cancelled' => 3, 'declined' => 5],
        };

        return $this->weighted($weights);
    }

    /**
     * One credential of an application: its purpose and whatever the type asks for.
     *
     * @return array{credential_type_id: int, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string}
     */
    private function item(CredentialType $type): array
    {
        $terms = $type->asks_term_and_year
            ? array_values(array_filter(array_map(fn (Term $term): string => $term->value, Term::cases()), fn (): bool => fake()->boolean(60))) ?: [Term::First->value]
            : null;

        return [
            'credential_type_id' => $type->id,
            'purpose' => $type->requires_receiving_institution ? 'Transfer to another school' : (string) fake()->randomElement($this->purposes ?: ['Employment']),
            'receiving_institution' => $type->requires_receiving_institution ? (string) fake()->randomElement(self::SCHOOLS) : null,
            'terms' => $terms,
            'academic_year' => $type->asks_term_and_year ? (string) fake()->randomElement(array_slice(CredentialType::academicYears(), 1, 8)) : null,
        ];
    }

    /**
     * One or two credentials, weighted, never the same one twice.
     *
     * @return list<array{credential_type_id: int, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string}>
     */
    private function items(bool $several): array
    {
        $weights = array_intersect_key(self::WEIGHTS, $this->types->all());
        $first = $this->weighted($weights);
        $items = [$this->item($this->types[$first])];

        if ($several && count($weights) > 1) {
            unset($weights[$first]);
            $items[] = $this->item($this->types[$this->weighted($weights)]);
        }

        return $items;
    }

    // ── Driving one application ──────────────────────────────────────────────────────────────

    /**
     * One application started $daysAgo days ago and taken to $outcome, with the detours that
     * happen along the way (REQ-31-03).
     *
     * @param  ?list<array{credential_type_id: int, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string}>  $items
     */
    private function application(int $daysAgo, string $outcome, ?User $owner = null, ?array $items = null, ?string $stopAt = null, ?bool $receive = null, bool $walkIn = false, bool $several = false): void
    {
        if ($this->types->isEmpty()) {
            return;
        }

        $this->at($daysAgo, function () use ($daysAgo, $outcome, $owner, $items, $stopAt, $receive, $walkIn, $several): void {
            DB::transaction(function () use ($daysAgo, $outcome, $owner, $items, $stopAt, $receive, $walkIn, $several): void {
                $this->registrar = fake()->randomElement($this->registrars);
                $owner ??= fake()->randomElement($this->requesters);
                $items ??= $this->items($several);

                // A walk-in is validated at the counter, so it starts at payment (spec 26).
                $lead = $walkIn && ($outcome === 'payment_check' || ! in_array($outcome, self::BEFORE_ACCEPTANCE, true))
                    ? $this->walkInFor($owner, $items, $outcome)
                    : $this->online($owner, $items, $outcome);

                if ($lead === null || ! in_array($lead->fresh()?->status, [RequestStatus::AcceptedForProcessing, RequestStatus::InProgress], true)) {
                    return;
                }

                if ($outcome === 'accepted') {
                    return;
                }

                foreach ([$lead, ...$lead->siblings()->get()->all()] as $request) {
                    $this->process($request, $outcome, $daysAgo, $stopAt, $receive ?? fake()->boolean(60));
                }
            });
        });
    }

    /**
     * Submitted online and taken through evaluation and payment as far as $outcome needs. Returns
     * the lead credential once accepted, or null when the outcome comes before acceptance.
     *
     * @param  list<array{credential_type_id: int, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string}>  $items
     */
    private function online(User $owner, array $items, string $outcome): ?CredentialRequest
    {
        $lead = $this->workflow->submit($owner, $items);

        if ($outcome === 'submitted') {
            return null;
        }

        if ($outcome === 'cancelled') {
            $this->later(fake()->numberBetween(2, 48));
            $this->workflow->cancel($lead, $owner, fake()->randomElement(['Requested the wrong document.', 'No longer needed.', 'Will request again with a different purpose.']));

            return null;
        }

        $this->later(fake()->numberBetween(2, 30));
        $this->workflow->startEvaluation($lead, $this->registrar);

        if ($outcome === 'evaluating') {
            return null;
        }

        // Declined at evaluation; the rest of the declined ones are declined at the final check.
        if ($outcome === 'declined' && fake()->boolean(70)) {
            $this->later(fake()->numberBetween(1, 24));
            $this->workflow->decline($lead, $this->registrar, fake()->randomElement([
                'The records show the requester has not completed the program.',
                'The document to be authenticated was not issued by UPHSL.',
                'Unsettled account at the Accounting Office.',
            ]));

            return null;
        }

        if ($outcome === 'clarification' || fake()->boolean(10)) {
            $this->later(fake()->numberBetween(1, 6));
            $this->check($lead, CheckStage::Evaluation, reject: CheckItem::CredentialRequest);
            $this->workflow->returnForClarification($lead, $this->registrar, 'Please state the purpose more specifically.');

            if ($outcome === 'clarification') {
                return null;
            }

            $this->later(fake()->numberBetween(12, 72));
            $this->workflow->resubmit($lead, $owner, 'Updated the purpose.');
            $this->later(fake()->numberBetween(2, 24));
            $this->workflow->startEvaluation($lead, $this->registrar);
        }

        if ($outcome === 'compliance' || fake()->boolean(8)) {
            $this->later(fake()->numberBetween(1, 6));
            $required = array_slice($lead->credentialType->requirements ?? [], 0, 2) ?: ['Completed clearance'];
            $this->workflow->requireOfficeVisit($lead, $this->registrar, $required, 'Please bring the originals to the Registrar\'s Office.');

            if ($outcome === 'compliance') {
                return null;
            }

            $this->later(fake()->numberBetween(24, 120));
            foreach ($lead->deficiencies()->get() as $deficiency) {
                $this->workflow->markReceived($deficiency, $this->registrar);
            }
            $this->workflow->confirmCompliance($lead, $this->registrar, 'Originals received.');
        } else {
            $this->later(fake()->numberBetween(1, 6));
            $this->check($lead, CheckStage::Evaluation);
            $this->workflow->approveEvaluation($lead, $this->registrar, 'Records and requirements complete.');
        }

        if ($lead->fresh()?->status === RequestStatus::AwaitingPayment) {
            if ($outcome === 'awaiting_payment') {
                return null;
            }

            $this->pay($lead);

            if (fake()->boolean(5)) {
                $this->later(fake()->numberBetween(2, 24));
                $this->workflow->rejectPayment($lead, $this->cashier, 'The amount on the receipt does not match the amount due.');
                $this->pay($lead);
            }

            if ($outcome === 'payment_check') {
                return null;
            }

            $this->later(fake()->numberBetween(2, 30));
            $this->workflow->verifyPaymentAndAccept($lead, $this->cashier);
        }

        return $lead;
    }

    /**
     * Recorded at the counter for a new client or one with an account, then paid there unless the
     * outcome is waiting for that payment.
     *
     * @param  list<array{credential_type_id: int, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string}>  $items
     */
    private function walkInFor(User $owner, array $items, string $outcome): CredentialRequest
    {
        $sex = fake()->boolean() ? Sex::Female : Sex::Male;
        $client = fake()->boolean(70) ? [
            'first_name' => (string) fake()->randomElement($sex === Sex::Female ? self::WOMEN : self::MEN),
            'middle_name' => null,
            'last_name' => (string) fake()->randomElement(self::SURNAMES),
            'sex' => $sex->value,
            'email' => 'walkin.'.fake()->unique()->numerify('#####').'@example.com',
        ] : [
            'first_name' => (string) $owner->first_name, 'middle_name' => $owner->middle_name, 'last_name' => (string) $owner->last_name,
            'sex' => $owner->sex->value ?? Sex::Male->value, 'email' => $owner->email,
        ];

        $request = $this->workflow->submitWalkIn($this->registrar, $items, [
            ...$client,
            'contact_number' => '+639'.fake()->numerify('#########'),
            'academic_status' => ($owner->academic_status ?? AcademicStatus::Graduate)->value,
            'college_id' => (int) $owner->college_id,
            'program' => (string) $owner->program,
            'student_number' => null,
            'attended_from' => (int) ($owner->attended_from ?? 2018),
            'attended_to' => (int) ($owner->attended_to ?? 2022),
        ]);

        // The amount due is set when the walk-in is recorded.
        if ($request->status === RequestStatus::PaymentVerification && $outcome !== 'payment_check') {
            $this->later(1);
            $this->workflow->verifyWalkInPayment($request, $this->registrar, 'OR-'.fake()->numerify('######'), (string) $request->amount_due);
        }

        return $request;
    }

    /**
     * One accepted credential taken through its offices to $outcome.
     */
    private function process(CredentialRequest $request, string $outcome, int $daysAgo, ?string $stopAt, bool $receive): void
    {
        $codes = $request->steps()->with('department')->orderBy('position')->get()->map(fn ($step) => (string) $step->department?->code)->values()->all();

        // A return for correction on the way, for some of those that carry on (REQ-31-03).
        if (in_array($outcome, ['ready', 'released'], true) && fake()->boolean(8)) {
            $this->returnForCorrection($request, $codes);
        }

        match ($outcome) {
            'in_progress' => $this->advanceUntil($request, $stopAt ?? (string) fake()->randomElement(array_slice($codes, 0, max(1, count($codes) - 1))), receive: $receive),
            'returned' => $this->returnForCorrection($request, $codes),
            'final_check' => $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: $receive, finalCheck: true),
            'declined' => $this->declineAtFinalCheck($request),
            'ready' => $this->advanceUntil($request, null),
            'released' => $this->released($request, $codes, $daysAgo),
            default => null,
        };
    }

    /**
     * Returned by a later office to an earlier one: the correction is waiting there.
     *
     * @param  list<string>  $codes  the route's office codes, in order
     */
    private function returnForCorrection(CredentialRequest $request, array $codes): void
    {
        // An office after the first and before the final check that is not the Registrar.
        $candidates = array_values(array_filter(array_keys($codes), fn (int $i): bool => $i > 0 && $i < count($codes) - 1 && $codes[$i] !== Department::REGISTRAR_CODE));

        if ($candidates === []) {
            return;
        }

        $at = (int) fake()->randomElement($candidates);
        $to = (string) fake()->randomElement(array_values(array_filter(array_slice($codes, 0, $at), fn (string $code): bool => $code !== $codes[$at])));

        $this->advanceUntil($request, $codes[$at], receive: true);
        $this->later(fake()->numberBetween(2, 24));
        $this->workflow->returnVisit($visit = $request->openVisit()->firstOrFail(), $this->actorFor($codes[$at]), Department::where('code', $to)->firstOrFail(),
            fake()->randomElement(['Name does not match the records.', 'Missing dry seal.', 'Wrong program printed.', 'Grades do not match the records.']),
            'Correct and reprint.');

        // The Registrar carries it back to that office (REQ-32-08).
        $this->later(fake()->numberBetween(1, 6));
        $this->workflow->forwardDocument($visit, $this->registrar);
    }

    private function declineAtFinalCheck(CredentialRequest $request): void
    {
        $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true, finalCheck: true);
        $this->later(fake()->numberBetween(1, 12));
        $this->workflow->decline($request, $this->registrar, 'An error was found at the final quality check that cannot be corrected.');
    }

    /**
     * Ready and claimed, some by a representative; a quarter of the older ones slowed at one office
     * so they come out late.
     *
     * @param  list<string>  $codes
     */
    private function released(CredentialRequest $request, array $codes, int $daysAgo): void
    {
        $representative = null;

        if (fake()->boolean(15) && $request->user_id !== null && $request->isLead()) {
            $owner = $request->student()->firstOrFail();
            $representative = $this->workflow->declareRepresentative($request, $owner,
                ['full_name' => fake()->randomElement(self::WOMEN).' '.fake()->randomElement(self::SURNAMES), 'relationship' => (string) fake()->randomElement(['Mother', 'Father', 'Sibling', 'Spouse']), 'contact_number' => '09'.fake()->numerify('#########')],
                $this->image('spa.png'), $this->image('representative-id.png'));
            $this->workflow->reviewRepresentative($representative, $this->registrar, true);
        }

        $slow = $daysAgo > 35 && fake()->boolean(25);
        $this->advanceUntil($request, null, slowAt: $slow ? (string) fake()->randomElement(array_slice($codes, 1, -1) ?: $codes) : null, slowHours: 24 * fake()->numberBetween(8, 16));

        if ($request->fresh()?->status !== RequestStatus::ReadyForRelease) {
            return;
        }

        $this->later(fake()->numberBetween(4, 120));
        $representative !== null
            ? $this->release($request, RecipientType::Representative, $representative->id)
            : $this->release($request);
    }

    /**
     * @template T of string
     *
     * @param  array<T, int>  $weights
     * @return T
     */
    private function weighted(array $weights): string
    {
        $pick = fake()->numberBetween(1, max(1, array_sum($weights)));

        foreach ($weights as $key => $weight) {
            $pick -= $weight;

            if ($pick <= 0) {
                return $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
