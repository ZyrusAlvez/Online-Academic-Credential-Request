<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    private const DEAN = "Academic verification and Dean's approval";

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
            ['LIB', 'Library', DepartmentType::Office, 'Library clearance, if required'],
            ['FIN', 'Cashier / Finance Office', DepartmentType::Office, 'Financial clearance, if required'],
            ['SAS', 'Student Affairs / Student Personnel Services', DepartmentType::Office, 'Student conduct or clearance verification, if required'],
            [Department::REGISTRAR_CODE, 'Office of the Registrar', DepartmentType::Office, 'Final records verification, processing, and release'],
        ];

        foreach ($departments as [$code, $name, $type, $description]) {
            Department::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'description' => $description],
            );
        }
    }
}
