<?php

namespace Database\Seeders;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
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
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local and staging only. The demo accounts (DemoSeeder) plus sample requests in every status of
 * the client's workflow, driven through the real RequestWorkflow so the ledger, office visits and
 * notifications are realistic. `php artisan app:reset-demo --requests` runs it (REQ-23-08).
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoRequestSeeder
 */
class DemoRequestSeeder extends Seeder
{
    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private RequestWorkflow $workflow;

    private User $registrar;

    private User $cashier;

    /** @var Collection<string, User> office code => staff account */
    private Collection $staff;

    public function run(RequestWorkflow $workflow): void
    {
        if (! app()->environment(['local', 'staging']) && ! app()->runningUnitTests()) {
            throw new RuntimeException('The demo seeder only runs in the local and staging environments.');
        }

        $this->call(DemoSeeder::class);

        $this->workflow = $workflow;
        $this->registrar = DemoSeeder::registrarAccount();
        $this->cashier = DemoSeeder::cashierAccount();
        $this->staff = Department::active()->get()->keyBy('code')->map(fn (Department $department) => DemoSeeder::staffAccount($department));

        $requesters = collect(DemoSeeder::REQUESTERS)->map(fn (array $r) => User::where('email', $r[0])->firstOrFail());

        // Every requester has a saved ID; it is checked on each request (spec 15).
        $requesters->each(function (User $requester): void {
            $this->at(40, fn () => app(SubmitIdentityDocument::class)->handle($requester, 'PhilSys National ID', $this->image('national-id.png')));
        });

        $type = fn (string $name): CredentialType => CredentialType::where('name', $name)->firstOrFail();

        DB::transaction(function () use ($requesters, $type): void {
            [$juan, $maria, $jose, $ana] = $requesters->all();
            $tor = $type('Transcript of Records (TOR)');

            // Submitted, waiting for an evaluator.
            $this->at(0, fn () => $this->submit($juan, $tor, 'Employment abroad'));

            // Under evaluation, half checked: the credential is fine, the ID is not (spec 16).
            $this->at(1, function () use ($maria, $type): void {
                $request = $this->submit($maria, $type('Certification of Enrollment'), 'Scholarship application');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->workflow->recordCheck($request, $this->registrar, CheckItem::CredentialRequest, CheckResult::Confirmed);
                $this->workflow->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);
            });

            // Pending compliance: the originals are required, so the requester must visit the office;
            // one of the two was already received (spec 20).
            $this->at(3, function () use ($jose, $tor): void {
                $request = $this->submit($jose, $tor, 'Board or licensure examination');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->check($request, CheckStage::Evaluation, reject: CheckItem::AcademicRecord);
                $this->workflow->requireOfficeVisit($request, $this->registrar, array_slice($tor->requirements ?? [], 0, 2) ?: ['Form 137'],
                    'Please submit the originals at the Registrar\'s Office, personally or through a representative with an SPA.');
                $this->later(24);
                $this->workflow->markReceived($request->deficiencies()->firstOrFail(), $this->registrar);
            });

            // Returned for clarification: the ID is too blurred; waiting for the requester to edit it.
            $this->at(1, function () use ($jose, $type): void {
                $request = $this->submit($jose, $type('Certification of Graduation'), 'Further studies');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->check($request, CheckStage::Evaluation, reject: CheckItem::Identity);
                $this->workflow->returnForClarification($request, $this->registrar, 'The photo of your ID is too blurred to read the name.');
            });

            // Resubmitted: returned for a blurred ID, the requester uploaded another and sent it back.
            $this->at(2, function () use ($ana, $type): void {
                $request = $this->submit($ana, $type('Copy of Grades'), 'Employment');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->check($request, CheckStage::Evaluation, reject: CheckItem::Identity);
                $this->workflow->returnForClarification($request, $this->registrar, 'The photo of your ID is too blurred to read the name.');
                $this->later(20);
                $identity = app(SubmitIdentityDocument::class)->handle($ana, 'Passport', $this->image('passport.png'));
                $this->workflow->resubmit($request, $ana, 'I uploaded a clearer photo of my passport.', identity: $identity);
            });

            // Awaiting payment.
            $this->at(2, function () use ($maria, $type): void {
                $request = $this->submit($maria, $type('Course Description'), 'Further studies');
                $this->evaluate($request);
            });

            // Payment verification.
            $this->at(1, function () use ($juan, $type): void {
                $request = $this->submit($juan, $type('Certification of Units Earned'), 'Employment');
                $this->evaluate($request);
                $this->pay($request);
            });

            // Accepted for processing, not yet received by the Registrar.
            $this->at(0, fn () => $this->accepted($jose, $type('Certification of Graduation'), 'Employment'));

            // In progress: received by the Dean of CBAA.
            $this->at(5, function () use ($maria, $tor): void {
                $request = $this->accepted($maria, $tor, 'Further studies');
                $this->advanceUntil($request, 'CBAA', receive: true);
            });

            // Returned for correction: VP Finance sent it back to the Registrar.
            $this->at(6, function () use ($juan, $tor): void {
                $request = $this->accepted($juan, $tor, 'Employment');
                $this->advanceUntil($request, 'VPF', receive: true);
                $this->later(5);
                $this->workflow->returnVisit($request->openVisit()->firstOrFail(), $this->staff['VPF'], Department::where('code', Department::REGISTRAR_CODE)->firstOrFail(),
                    'Name on the TOR does not match the records.', 'Correct the middle name and reprint the TOR.');
            });

            // For final approval: back at the Registrar for the final quality check.
            $this->at(7, function () use ($jose, $type): void {
                $request = $this->accepted($jose, $type('Transfer Certificate'), 'Transfer to another school', 'Polytechnic University of the Philippines');
                $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true, finalCheck: true);
            });

            // Ready for release, to be claimed by a verified representative.
            $this->at(9, function () use ($maria, $type): void {
                $request = $this->accepted($maria, $type('Copy of Grades'), 'Employment');
                $this->workflow->declareRepresentative($request, $maria, ['full_name' => 'Rosa Reyes', 'relationship' => 'Mother', 'contact_number' => '09185550101'],
                    UploadedFile::fake()->create('spa.pdf', 120, 'application/pdf'), $this->image('rosa-id.png'));
                $this->workflow->reviewRepresentative($request->representatives()->firstOrFail(), $this->registrar, true);
                $this->advanceUntil($request, null);
            });

            // Released on time.
            $this->at(14, function () use ($juan, $type): void {
                $request = $this->accepted($juan, $type('Certification of Enrollment'), 'Visa or immigration');
                $this->advanceUntil($request, null);
                $this->later(20);
                $this->release($request);
            });

            // Released late: the Chancellor's signature took long.
            $this->at(35, function () use ($jose, $tor): void {
                $request = $this->accepted($jose, $tor, 'Employment');
                $this->advanceUntil($request, 'CHAN', receive: true);
                $this->later(24 * 12);
                $this->advanceUntil($request, null);
                $this->later(30);
                $this->release($request);
            });

            // Overdue: waiting for the Chancellor's signature (no delay reasons, REQ-24-09).
            $this->at(21, function () use ($maria, $tor): void {
                $request = $this->accepted($maria, $tor, 'Board or licensure examination');
                $this->advanceUntil($request, 'CHAN', receive: true);
                $this->later(48);
            });

            // Accepted, received by the Registrar for preparation: not yet in progress (REQ-16-18).
            $this->at(8, function () use ($juan, $type): void {
                $request = $this->accepted($juan, $type('Diploma / Certificate for Non-Degree Programs'), 'Personal copy');
                $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true);
            });

            // Declined at the final quality check (REQ-16-19).
            $this->at(10, function () use ($ana, $type): void {
                $request = $this->accepted($ana, $type('Certification of Graduation'), 'Employment');
                $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true, finalCheck: true);
                $this->workflow->decline($request, $this->registrar, 'The records show the requester has not completed the program.');
            });

            // Declined: not eligible.
            $this->at(4, function () use ($ana, $type): void {
                $request = $this->submit($ana, $type('Authentication'), 'Employment abroad');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->workflow->decline($request, $this->registrar, 'The document to be authenticated was not issued by UPHSL.');
            });

            // Cancelled by the requester.
            $this->at(3, function () use ($jose, $type): void {
                $request = $this->submit($jose, $type('Medium of Instruction'), 'Scholarship application');
                $this->workflow->cancel($request, $jose, 'Requested the wrong document.');
            });

            // An application of two credentials: evaluated and paid once, then each on its own route (spec 27).
            $this->at(6, function () use ($ana, $tor, $type): void {
                $request = $this->workflow->submit($ana, [
                    ['credential_type_id' => $tor->id, 'purpose' => 'Further studies', 'receiving_institution' => null],
                    ['credential_type_id' => $type('Certification of Enrollment')->id, 'purpose' => 'Further studies', 'receiving_institution' => null],
                ]);
                $this->evaluate($request);
                $this->pay($request);
                $this->later(4);
                $this->workflow->verifyPaymentAndAccept($request, $this->cashier);
            });

            // Walk-in, recorded at the counter; the client has no account yet (REQ-26-11).
            $this->at(0, fn () => $this->walkIn($type('Certification of Enrollment'), 'Scholarship application', [
                'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'email' => 'pedro.villanueva@example.com',
                'college' => 'CCS', 'program' => 'Bachelor of Science in Information Technology, Specialization in Game Development',
            ]));

            // Walk-in by a demo requester: linked to Ana's account, paid at the counter and in progress.
            $this->at(4, function () use ($ana, $tor): void {
                $request = $this->walkIn($tor, 'Employment', [
                    'first_name' => $ana->first_name, 'middle_name' => $ana->middle_name, 'last_name' => $ana->last_name, 'email' => $ana->email,
                    'college' => 'CTE', 'program' => (string) $ana->program,
                ]);
                $this->later(1);
                $this->workflow->verifyWalkInPayment($request, $this->registrar, 'OR-'.fake()->numerify('######'), (string) $request->amount_due);
            });
        });
    }

    /**
     * A walk-in request recorded by the demo registrar (spec 26).
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, email: string, college: string, program: string}  $client
     */
    private function walkIn(CredentialType $type, string $purpose, array $client): CredentialRequest
    {
        $college = Department::where('code', $client['college'])->firstOrFail();

        return $this->workflow->submitWalkIn($this->registrar, ['credential_type_id' => $type->id, 'purpose' => $purpose, 'receiving_institution' => null], [
            'first_name' => $client['first_name'],
            'middle_name' => $client['middle_name'],
            'last_name' => $client['last_name'],
            'email' => $client['email'],
            'contact_number' => '0917'.fake()->numerify('#######'),
            'academic_status' => AcademicStatus::Graduate->value,
            'college_id' => $college->id,
            'program' => $client['program'],
            'student_number' => null,
            'attended_from' => 2019,
            'attended_to' => 2023,
        ]);
    }

    private function submit(User $owner, CredentialType $type, string $purpose, ?string $receivingSchool = null): CredentialRequest
    {
        return $this->workflow->submit($owner, [
            'credential_type_id' => $type->id,
            'purpose' => $purpose,
            'receiving_institution' => $receivingSchool,
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

    private function accepted(User $owner, CredentialType $type, string $purpose, ?string $receivingSchool = null): CredentialRequest
    {
        $request = $this->submit($owner, $type, $purpose, $receivingSchool);
        $this->evaluate($request);

        // A free credential is accepted when its evaluation is complete.
        if ($request->requires_payment) {
            $this->pay($request);
            $this->later(4);
            // Online payments are the cashier's to validate (REQ-25-04).
            $this->workflow->verifyPaymentAndAccept($request, $this->cashier);
        }

        return $request;
    }

    /**
     * Receive and complete visits in order until the document reaches $stopAt (and is received
     * there when $receive), or until it is ready for release when $stopAt is null.
     */
    private function advanceUntil(CredentialRequest $request, ?string $stopAt, bool $receive = false, bool $finalCheck = false): void
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

            $this->later(fake()->numberBetween(3, 30));

            $this->workflow->completeVisit($visit, $this->actorFor($code), fake()->optional(0.3)->randomElement(['Signed.', 'Verified.', 'Stamped.']));

            // Done, then the office sends the paper on a little later (REQ-17-07).
            if ($visit->status === VisitStatus::Done) {
                $this->later(fake()->numberBetween(1, 6));
                $this->workflow->forwardDocument($visit, $this->actorFor($code));
            }
        }
    }

    private function release(CredentialRequest $request): void
    {
        $this->workflow->release($request, $this->registrar, RecipientType::Owner);
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
     * Move the clock forward by some hours (office actions take time).
     */
    private function later(int $hours): void
    {
        Carbon::setTestNow(Carbon::now()->addHours($hours));
    }

    /**
     * Run $callback as if it started $daysAgo days ago (ledger times follow).
     */
    private function at(int $daysAgo, callable $callback): void
    {
        Carbon::setTestNow(Carbon::now()->subDays($daysAgo)->setTime(fake()->numberBetween(8, 10), fake()->numberBetween(0, 59)));

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
