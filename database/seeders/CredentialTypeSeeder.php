<?php

namespace Database\Seeders;

use App\Models\CredentialType;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Default credential types and workflows. Workflows are only created for new types so
 * re-seeding never overwrites the registrar's edits. Requires DepartmentSeeder.
 */
class CredentialTypeSeeder extends Seeder
{
    private const COLLEGE = 'college';

    public function run(): void
    {
        $types = [
            ['Transcript of Records', true, 10, 'Official record of all subjects taken and grades earned.', ['LIB', 'FIN', self::COLLEGE, 'REG']],
            ['Certificate of Enrollment', false, 3, 'Certifies current or past enrollment for a term.', ['REG']],
            ['Certificate of Grades', false, 5, 'Grades for a specific term or school year.', [self::COLLEGE, 'REG']],
            ['Good Moral Certificate', false, 5, 'Certifies good conduct while enrolled.', ['SAS', 'REG']],
            ['Honorable Dismissal / Transfer Credentials', true, 10, 'For students transferring to another school.', ['LIB', 'FIN', 'SAS', self::COLLEGE, 'REG']],
            ['Diploma – Certified True Copy', true, 7, 'Certified true copy of the diploma.', ['FIN', 'REG']],
            ['CAV – Certification, Authentication and Verification', true, 10, 'Authentication of records for CHED/DFA or employment abroad.', [self::COLLEGE, 'REG']],
        ];

        $departments = Department::pluck('id', 'code');

        foreach ($types as [$name, $paid, $days, $description, $steps]) {
            $type = CredentialType::firstOrCreate(['name' => $name], [
                'description' => $description,
                'requires_payment' => $paid,
                'processing_days' => $days,
            ]);

            if (! $type->wasRecentlyCreated) {
                continue;
            }

            foreach ($steps as $index => $step) {
                $type->steps()->create([
                    'position' => $index + 1,
                    'department_id' => $step === self::COLLEGE ? null : $departments[$step],
                    'is_student_college' => $step === self::COLLEGE,
                ]);
            }
        }
    }
}
