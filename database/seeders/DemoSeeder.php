<?php

namespace Database\Seeders;

use App\Actions\Identity\ReviewIdentityDocument;
use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\DelayCategory;
use App\Enums\RecipientType;
use App\Enums\Role;
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
 * Local and staging only. Demo accounts and requests in every status of the client's workflow,
 * driven through the real RequestWorkflow so the ledger, office visits and notifications are
 * realistic. Password for every account: "password".
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private RequestWorkflow $workflow;

    private User $registrar;

    /** @var Collection<string, User> office code => staff account */
    private Collection $staff;

    public function run(RequestWorkflow $workflow): void
    {
        if (! app()->environment(['local', 'staging']) && ! app()->runningUnitTests()) {
            throw new RuntimeException('The demo seeder only runs in the local and staging environments.');
        }

        $this->call([DepartmentSeeder::class, CredentialTypeSeeder::class, RegistrarSeeder::class, NonWorkingDaySeeder::class]);

        $this->workflow = $workflow;
        $this->registrar = User::where('role', Role::Registrar)->orderBy('id')->firstOrFail();

        $departments = Department::active()->get()->keyBy('code');
        $this->staff = $departments->map(fn (Department $department) => $this->staffAccount($department));

        $requesters = collect([
            ['student@example.com', 'Juan', 'Santos', 'Dela Cruz', 'CCS', 'BS Computer Science'],
            ['maria@example.com', 'Maria', null, 'Reyes', 'CBA', 'BS Accountancy'],
            ['jose@example.com', 'Jose', 'Lopez', 'Garcia', 'COE', 'BS Civil Engineering'],
            ['ana@example.com', 'Ana', null, 'Mendoza', 'COED', 'Bachelor of Secondary Education'],
        ])->map(fn (array $r) => $this->requester($departments[$r[4]], ...array_slice($r, 0, 4), program: $r[5]));

        // Identities: three verified, Ana's ID still waiting for the registrar.
        $requesters->each(function (User $requester, int $index): void {
            $this->at(40, function () use ($requester, $index): void {
                $document = app(SubmitIdentityDocument::class)->handle($requester, 'PhilSys National ID', UploadedFile::fake()->image('national-id.png', 800, 500));

                if ($index < 3) {
                    app(ReviewIdentityDocument::class)->handle($document, $this->registrar, verified: true);
                }
            });
        });

        $type = fn (string $name): CredentialType => CredentialType::where('name', $name)->firstOrFail();

        DB::transaction(function () use ($requesters, $type): void {
            [$juan, $maria, $jose, $ana] = $requesters->all();
            $tor = $type('Transcript of Records (TOR)');

            // Submitted, waiting for an evaluator.
            $this->at(0, fn () => $this->submit($juan, $tor, 'Employment abroad'));

            // Under evaluation.
            $this->at(1, function () use ($maria, $type): void {
                $request = $this->submit($maria, $type('Certification of Enrollment'), 'Scholarship application');
                $this->workflow->startEvaluation($request, $this->registrar);
            });

            // Pending compliance: two missing originals, one already brought in.
            $this->at(3, function () use ($jose, $tor): void {
                $request = $this->submit($jose, $tor, 'Board examination');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->workflow->requestCompliance($request, $this->registrar, [
                    'Form 137 or the UPHSL copy of the previous Transcript of Records',
                    'PSA-authenticated birth certificate',
                ], 'Please submit the originals at the Registrar\'s Office.');
                $this->later(20);
                $this->workflow->resolveDeficiency($request->deficiencies()->firstOrFail(), $this->registrar, 'Original Form 137 received and verified.');
            });

            // Returned for customer action before acceptance (clarification).
            $this->at(2, function () use ($ana, $type): void {
                $request = $this->submit($ana, $type('Copy of Grades'), 'Job application');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->workflow->requestCustomerAction($request, $this->registrar, 'Our records show two enrollments under different names. Please confirm the name used in 2021.');
            });

            // Awaiting payment.
            $this->at(2, function () use ($maria, $type): void {
                $request = $this->submit($maria, $type('Course Description'), 'Credit evaluation abroad');
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

            // In progress: received by the Dean of CBA.
            $this->at(5, function () use ($maria, $tor): void {
                $request = $this->accepted($maria, $tor, 'Graduate school application');
                $this->advanceUntil($request, 'CBA', receive: true);
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
                $request = $this->accepted($jose, $type('Transfer Certificate'), 'Transfer', 'Polytechnic University of the Philippines');
                $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true, finalCheck: true);
            });

            // Ready for release, to be claimed by a verified representative.
            $this->at(9, function () use ($maria, $type): void {
                $request = $this->accepted($maria, $type('Copy of Grades'), 'Employment');
                $this->workflow->declareRepresentative($request, $maria, ['full_name' => 'Rosa Reyes', 'relationship' => 'Mother', 'contact_number' => '0918 555 0101'],
                    UploadedFile::fake()->create('spa.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('rosa-id.png'));
                $this->workflow->reviewRepresentative($request->representatives()->firstOrFail(), $this->registrar, true);
                $this->advanceUntil($request, null);
            });

            // Released on time.
            $this->at(14, function () use ($juan, $type): void {
                $request = $this->accepted($juan, $type('Certification of Enrollment'), 'Visa application');
                $this->advanceUntil($request, null);
                $this->later(20);
                $this->release($request, $juan->name);
            });

            // Released late: the Chancellor's signature took long.
            $this->at(35, function () use ($jose, $tor): void {
                $request = $this->accepted($jose, $tor, 'Employment');
                $this->advanceUntil($request, 'CHAN', receive: true);
                $this->later(24 * 12);
                $this->advanceUntil($request, null);
                $this->later(30);
                $this->release($request, $jose->name);
            });

            // Overdue: waiting for the Chancellor's signature, with a delay reason.
            $this->at(21, function () use ($maria, $tor): void {
                $request = $this->accepted($maria, $tor, 'Board examination');
                $this->advanceUntil($request, 'CHAN', receive: true);
                $this->later(48);
                $this->workflow->recordDelay($request, $this->staff['CHAN'], DelayCategory::PendingSignature, 'The Chancellor is attending a conference abroad.', 'Signing scheduled on return.');
            });

            // Paused: returned to the requester after acceptance.
            $this->at(8, function () use ($juan, $type): void {
                $request = $this->accepted($juan, $type('Diploma / Certificate for Non-Degree Programs'), 'Personal copy');
                $this->advanceUntil($request, Department::REGISTRAR_CODE, receive: true);
                $this->later(4);
                $this->workflow->requestCustomerAction($request, $this->registrar, 'Please submit an affidavit of loss for the original diploma.');
            });

            // Declined: not eligible.
            $this->at(4, function () use ($ana, $type): void {
                $request = $this->submit($ana, $type('Authentication'), 'Employment abroad');
                $this->workflow->startEvaluation($request, $this->registrar);
                $this->workflow->decline($request, $this->registrar, 'The document to be authenticated was not issued by UPHSL.');
            });

            // Cancelled by the requester.
            $this->at(3, function () use ($jose, $type): void {
                $request = $this->submit($jose, $type('Medium of Instruction'), 'Scholarship');
                $this->workflow->cancel($request, $jose, 'Requested the wrong document.');
            });
        });
    }

    private function staffAccount(Department $department): User
    {
        $user = User::firstOrNew(['email' => strtolower($department->code).'@example.com']);
        $user->fill(['first_name' => $department->code, 'middle_name' => null, 'last_name' => 'Staff', 'password' => 'password']);
        $user->forceFill(['role' => Role::Department, 'department_id' => $department->id, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    private function requester(Department $college, string $email, string $first, ?string $middle, string $last, string $program): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'password' => 'password',
            'contact_number' => '0917 '.fake()->numerify('### ####'),
            'academic_status' => AcademicStatus::Graduate,
            'college_id' => $college->id,
            'program' => $program,
            'student_number' => sprintf('%02d-%04d-%03d', fake()->numberBetween(15, 20), fake()->numberBetween(1, 9999), fake()->numberBetween(1, 999)),
            'attended_from' => 2020,
            'attended_to' => 2024,
        ]);
        $user->forceFill(['role' => Role::Student, 'is_active' => true, 'email_verified_at' => now(), 'privacy_consented_at' => now()])->save();

        return $user;
    }

    private function submit(User $owner, CredentialType $type, string $purpose, ?string $receivingSchool = null): CredentialRequest
    {
        return $this->workflow->submit($owner, [
            'credential_type_id' => $type->id,
            'copies' => fake()->numberBetween(1, 2),
            'purpose' => $purpose,
            'receiving_institution' => $receivingSchool,
        ]);
    }

    private function evaluate(CredentialRequest $request): void
    {
        $this->later(2);
        $this->workflow->startEvaluation($request, $this->registrar);
        $this->later(3);
        $this->workflow->completeEvaluation($request, $this->registrar, 'Records and requirements complete.');
    }

    private function pay(CredentialRequest $request): void
    {
        $this->later(5);
        $this->workflow->submitPaymentProof($request, $request->student()->firstOrFail(), UploadedFile::fake()->image('receipt.png', 600, 800),
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
            $this->workflow->verifyPaymentAndAccept($request, $this->registrar, 'OR-'.fake()->numerify('######'));
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
        }
    }

    private function release(CredentialRequest $request, string $recipient): void
    {
        $this->workflow->release($request, $this->registrar, [
            'recipient_type' => RecipientType::Owner,
            'recipient_name' => $recipient,
            'id_presented' => 'PhilSys National ID',
            'identity_checked' => true,
            'acknowledged' => true,
        ]);
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
