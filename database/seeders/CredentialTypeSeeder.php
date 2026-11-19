<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\RouteTask;
use App\Models\CredentialType;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The credential master list from the client's Proposed Workflow, section VII (spec 03). Only
 * adds missing types, so seeding again never overwrites the Admin's edits (REQ-23-03).
 * Requires DepartmentSeeder.
 */
class CredentialTypeSeeder extends Seeder
{
    private const DEAN = 'dean';

    private const CLEARANCE = 'Completed clearance (Library, Accounting and other offices)';

    private const ADMISSION = 'Complete admission requirements: Form 137 or the UPHSL copy of the previous Transcript of Records';

    private const BIRTH_CERTIFICATE = 'PSA-authenticated birth certificate';

    private const RECEIVING_SCHOOL = 'The complete name of the receiving school is required.';

    /**
     * Per-type options (spec 29): the Semestral Report asks for the terms and year and is paid per
     * term; graduates get the Certificate of Grades free.
     *
     * @var array<string, array<string, mixed>>
     */
    private const OPTIONS = [
        'Semestral Report of Grades' => ['asks_term_and_year' => true, 'fee_per_term' => true],
        'Certificate of Grades' => ['free_for' => [AcademicStatus::Graduate->value]],
    ];

    /**
     * Seeded office days that differ from the automatic split (REQ-30-02), in route order.
     *
     * @var array<string, list<int>>
     */
    private const SPLITS = [
        'Transcript of Records (TOR)' => [1, 2, 2, 2],
        'TOR for Transfer Credentials' => [1, 2, 2, 2],
    ];

    /** code => [task, instructions] */
    private const STEPS = [
        'REG' => [RouteTask::Prepare, 'Verify academic records and prepare the credential.'],
        self::DEAN => [RouteTask::Sign, 'Review and affix the required signature.'],
        'VPF' => [RouteTask::DocumentaryStamp, 'Process the documentary stamp. No signature is required.'],
        'CHAN' => [RouteTask::Sign, 'Review and affix the required signature.'],
        'PRES' => [RouteTask::Sign, 'Affix the required signature.'],
    ];

    public function run(): void
    {
        foreach ($this->types() as $name => $attributes) {
            $type = CredentialType::firstOrCreate(['name' => $name], $attributes['type']);

            if ($type->wasRecentlyCreated) {
                $this->createSteps($type, $attributes['route']);
            }
        }
    }

    /**
     * @return array<string, array{type: array<string, mixed>, route: list<string>}>
     */
    private function types(): array
    {
        $registrarOnly = ['REG'];
        $certification = ['REG', 'VPF', 'CHAN'];
        $diploma = ['REG', 'CHAN', 'PRES'];
        $tor = ['REG', self::DEAN, 'VPF', 'CHAN'];

        $clearance = [self::CLEARANCE];
        $records = [self::CLEARANCE, self::ADMISSION, self::BIRTH_CERTIFICATE];

        $list = [
            ['Authentication', '50.00', 1, 'Authentication of a UPHSL-issued document.', $clearance, false, $registrarOnly],
            ['Certification, Authentication, Verification (CAV)', '500.00', 2, 'Certification, authentication and verification of records, e.g. for CHED or DFA.', $clearance, false, $registrarOnly],
            ['Certification of Graduation', '230.00', 6, 'Certifies that the requester graduated from a program.', $records, false, $certification],
            ['Certification of Enrollment', '230.00', 6, 'Certifies current or past enrollment.', $clearance, false, $certification],
            ['Certification of Units Earned', '230.00', 6, 'Certifies the academic units earned.', $clearance, false, $certification],
            ['Course Description', '230.00', 6, 'Descriptions of the courses taken.', $clearance, false, $certification],
            ['Medium of Instruction', '230.00', 6, 'Certifies the language used as the medium of instruction.', $clearance, false, $certification],
            ['Certificate of Grades', '230.00', 6, 'Certified grades for a term or school year.', $clearance, false, $certification],
            ['Semestral Report of Grades', '100.00', 6, 'Report of grades for the chosen terms of one academic year.', $clearance, false, $certification],
            ['Diploma / Certificate for Non-Degree Programs', '800.00', 6, 'Diploma, or the certificate of a non-degree program.', $records, false, $diploma],
            ['Notice of Acceptance', '230.00', 6, 'Notice of acceptance issued by the Registrar.', $clearance, false, $certification],
            ['Letter for the BI Commissioner', '230.00', 6, 'Letter to the Bureau of Immigration Commissioner, for foreign students.', $clearance, false, $certification],
            ['Permit to Cross-Enroll', '230.00', 6, 'Permit to take courses in another school.', $clearance, false, $certification],
            ['Transcript of Records (TOR)', '530.00', 7, 'Official record of all courses taken and grades earned.', $records, false, $tor],
            ['Certificate of Transfer', '230.00', 6, 'Transfer credential (honorable dismissal) for moving to another school.', $records, true, $certification],
            ['Copy of Grades for Transfer Credentials', '230.00', 6, 'Copy of grades sent with the transfer credentials.', $records, true, $certification],
            ['TOR for Transfer Credentials', '530.00', 7, 'Transcript of Records sent with the transfer credentials.', $records, true, $tor],
        ];

        $types = [];

        foreach ($list as [$name, $fee, $days, $description, $requirements, $transfer, $route]) {
            $types[$name] = [
                'type' => [
                    'description' => $description,
                    'requires_payment' => true,
                    'fee' => $fee,
                    'processing_days' => $days,
                    'requirements' => $requirements,
                    'special_conditions' => $transfer ? self::RECEIVING_SCHOOL : null,
                    'requires_receiving_institution' => $transfer,
                    ...self::options($name),
                ],
                'route' => $route,
            ];
        }

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(string $name): array
    {
        return self::OPTIONS[$name] ?? [];
    }

    /**
     * @param  list<string>  $route  department codes, or self::DEAN for the requester's college
     */
    private function createSteps(CredentialType $type, array $route): void
    {
        $departments = Department::pluck('id', 'code');
        $split = self::SPLITS[$type->name] ?? CredentialType::splitDays($type->processing_days, count($route));

        foreach ($route as $index => $code) {
            [$task, $instructions] = self::STEPS[$code];

            $type->steps()->create([
                'position' => $index + 1,
                'department_id' => $code === self::DEAN ? null : $departments[$code],
                'is_student_college' => $code === self::DEAN,
                'task' => $task,
                'instructions' => $instructions,
                'allotted_days' => $split[$index],
            ]);
        }
    }
}
