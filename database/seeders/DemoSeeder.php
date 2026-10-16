<?php

namespace Database\Seeders;

use App\Enums\Role;
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
 * Local and staging only. Demo accounts and requests in every status, driven through the real workflow so the
 * ledger, steps and notifications are realistic. Password for every account: "password".
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private RequestWorkflow $workflow;

    private User $registrar;

    public function run(RequestWorkflow $workflow): void
    {
        if (! app()->environment(['local', 'staging']) && ! app()->runningUnitTests()) {
            throw new RuntimeException('The demo seeder only runs in the local and staging environments.');
        }

        $this->call([DepartmentSeeder::class, CredentialTypeSeeder::class, RegistrarSeeder::class]);

        $this->workflow = $workflow;
        $this->registrar = User::where('role', Role::Registrar)->orderBy('id')->firstOrFail();

        $departments = Department::all()->keyBy('code');
        $staff = $departments->map(fn (Department $department) => $this->staff($department));

        $students = collect([
            ['student@example.com', 'Juan', 'Santos', 'Dela Cruz'],
            ['maria@example.com', 'Maria', null, 'Reyes'],
            ['jose@example.com', 'Jose', 'Lopez', 'Garcia'],
            ['ana@example.com', 'Ana', null, 'Mendoza'],
        ])->map(fn (array $s) => $this->student(...$s));

        $types = CredentialType::pluck('id', 'name');
        $tor = CredentialType::findOrFail($types['Transcript of Records']);
        $enrollment = CredentialType::findOrFail($types['Certificate of Enrollment']);
        $goodMoral = CredentialType::findOrFail($types['Good Moral Certificate']);
        $grades = CredentialType::findOrFail($types['Certificate of Grades']);

        DB::transaction(function () use ($students, $departments, $staff, $tor, $enrollment, $goodMoral, $grades): void {
            [$juan, $maria, $jose, $ana] = $students->all();

            // Awaiting payment (fresh).
            $this->at(0, fn () => $this->submit($juan, $tor, $departments['CCS']));

            // Payment under review.
            $this->at(1, function () use ($maria, $tor, $departments) {
                $request = $this->submit($maria, $tor, $departments['CBA']);
                $this->pay($request, $maria);
            });

            // Free request under registrar review.
            $this->at(1, fn () => $this->submit($ana, $enrollment, $departments['COED']));

            // In progress at the Library, on hold.
            $this->at(4, function () use ($jose, $tor, $departments, $staff) {
                $request = $this->routed($jose, $tor, $departments['COE']);
                $this->workflow->hold($request->currentStep()->firstOrFail(), $staff['LIB'], 'Unreturned book: Engineering Mechanics (Hibbeler).');
            });

            // In progress at the student's college.
            $this->at(6, function () use ($juan, $tor, $departments, $staff) {
                $request = $this->routed($juan, $tor, $departments['CCS']);
                $this->signUntil($request, 'CCS', $staff);
            });

            // Overdue: routed three weeks ago, stuck at the Cashier.
            $this->at(21, function () use ($ana, $tor, $departments, $staff) {
                $request = $this->routed($ana, $tor, $departments['COED']);
                $this->signUntil($request, 'FIN', $staff);
            });

            // At Student Affairs.
            $this->at(2, fn () => $this->routed($maria, $goodMoral, $departments['CBA']));

            // Ready for release.
            $this->at(9, function () use ($jose, $grades, $departments, $staff) {
                $request = $this->routed($jose, $grades, $departments['COE']);
                $this->signUntil($request, null, $staff);
            });

            // Released.
            $this->at(14, function () use ($maria, $enrollment, $departments, $staff) {
                $request = $this->routed($maria, $enrollment, $departments['CBA']);
                $this->signUntil($request, null, $staff);
                $this->workflow->release($request, $this->registrar, 'Claimed by the student.');
            });

            // Cancelled by the student.
            $this->at(3, function () use ($ana, $goodMoral, $departments) {
                $request = $this->submit($ana, $goodMoral, $departments['COED']);
                $this->workflow->cancel($request, $ana, 'Requested the wrong document.');
            });
        });
    }

    private function staff(Department $department): User
    {
        $user = User::firstOrNew(['email' => strtolower($department->code).'@example.com']);
        $user->fill(['first_name' => $department->code, 'middle_name' => null, 'last_name' => 'Staff', 'password' => 'password']);
        $user->forceFill(['role' => Role::Department, 'department_id' => $department->id, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    private function student(string $email, string $first, ?string $middle, string $last): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'password' => 'password']);
        $user->forceFill(['role' => Role::Student, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    private function submit(User $student, CredentialType $type, Department $college): CredentialRequest
    {
        return $this->workflow->submit($student, [
            'credential_type_id' => $type->id,
            'college_id' => $college->id,
            'student_number' => sprintf('%02d-%04d-%03d', fake()->numberBetween(15, 24), fake()->numberBetween(1, 9999), fake()->numberBetween(1, 999)),
            'program' => fake()->randomElement(['BS Computer Science', 'BS Accountancy', 'BS Civil Engineering', 'Bachelor of Secondary Education']),
            'student_status' => fake()->randomElement(['current', 'alumni']),
            'last_school_year' => '2024-2025',
            'purpose' => fake()->randomElement(['Employment', 'Board examination', 'Transfer to another school', 'Scholarship application']),
            'copies' => fake()->numberBetween(1, 3),
        ]);
    }

    private function pay(CredentialRequest $request, User $student): void
    {
        $this->workflow->submitPaymentProof($request, $student, UploadedFile::fake()->image('receipt.png', 600, 800), 'OR-'.fake()->numerify('########'), '300.00');
    }

    private function routed(User $student, CredentialType $type, Department $college): CredentialRequest
    {
        $request = $this->submit($student, $type, $college);

        if ($request->requires_payment) {
            $this->pay($request, $student);
        }

        $this->workflow->approve($request, $this->registrar, 'Verified.');

        return $request->fresh() ?? $request;
    }

    /**
     * Sign steps in order until the step of $stopAt is current (or all steps when null).
     *
     * @param  Collection<string, User>  $staff
     */
    private function signUntil(CredentialRequest $request, ?string $stopAt, $staff): void
    {
        while ($step = $request->currentStep()->with('department')->first()) {
            if ($step->department->code === $stopAt) {
                return;
            }

            Carbon::setTestNow(Carbon::now()->addHours(fake()->numberBetween(3, 20)));
            $this->workflow->sign($step, $staff[$step->department->code], fake()->optional(0.4)->randomElement(['Cleared.', 'No accountabilities.', 'Verified records.']));
        }
    }

    /**
     * Run $callback as if it happened $daysAgo days ago (ledger times follow).
     */
    private function at(int $daysAgo, callable $callback): void
    {
        Carbon::setTestNow(Carbon::now()->subDays($daysAgo)->setTime(fake()->numberBetween(8, 15), fake()->numberBetween(0, 59)));

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
