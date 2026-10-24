<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The offices of the credential master list (spec 02): the colleges acting as the Dean, the
 * Registrar, VP Finance, Chancellor and President. Safe to run again.
 */
class DepartmentSeeder extends Seeder
{
    private const DEAN = "Dean's review and signature";

    /** Offices replaced by the evaluator's clearance check (REQ-02-13); kept for history. */
    public const RETIRED = ['LIB', 'FIN', 'SAS'];

    public function run(): void
    {
        $departments = [
            ['CAS', 'College of Arts and Sciences', DepartmentType::College, self::DEAN],
            ['CBA', 'College of Business and Accountancy', DepartmentType::College, self::DEAN],
            ['CCS', 'College of Computer Studies', DepartmentType::College, self::DEAN],
            ['CRIM', 'College of Criminology', DepartmentType::College, self::DEAN],
            ['COED', 'College of Education', DepartmentType::College, self::DEAN],
            ['COE', 'College of Engineering', DepartmentType::College, self::DEAN],
            ['CIHM', 'College of International Hospitality Management', DepartmentType::College, self::DEAN],
            ['CME', 'College of Maritime Education', DepartmentType::College, self::DEAN],
            ['LAW', 'College of Law', DepartmentType::College, self::DEAN],
            ['GS', 'Graduate School', DepartmentType::College, self::DEAN],
            ['CAA', 'Architecture and Aviation', DepartmentType::College, self::DEAN],
            [Department::REGISTRAR_CODE, 'Office of the Registrar', DepartmentType::Office, 'Verifies records, prepares the credential and does the final quality check'],
            ['VPF', 'Office of the VP for Finance', DepartmentType::Office, 'Documentary stamp (no signature)'],
            ['CHAN', 'Office of the Chancellor', DepartmentType::Office, 'Review and signature'],
            ['PRES', 'Office of the President', DepartmentType::Office, 'Signature on diplomas and certificates'],
        ];

        foreach ($departments as [$code, $name, $type, $description]) {
            Department::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'description' => $description],
            );
        }

        Department::whereIn('code', self::RETIRED)->update(['is_active' => false]);
    }
}
