<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The offices of the credential master list (spec 02): the colleges acting as the Dean, the
 * Registrar, VP Finance, Chancellor and President. Only adds what is missing, so running it again
 * keeps the Admin's changes (REQ-23-03).
 */
class DepartmentSeeder extends Seeder
{
    public const DEAN = "Dean's review and signature";

    /**
     * The 11 colleges, code => name (list of 2026-11-06, REQ-02-01).
     *
     * @var array<string, string>
     */
    public const COLLEGES = [
        'CAS' => 'College of Arts and Sciences',
        'SOA' => 'School of Aviation',
        'BED' => 'Basic Education Department',
        'CBAA' => 'College of Business Administration and Accountancy',
        'CCS' => 'College of Computer Studies',
        'CCJE' => 'College of Criminal Justice Education',
        'CTE' => 'College of Teacher Education',
        'CEA' => 'College of Engineering and Architecture',
        'GS' => 'Graduate School',
        'CNHTM' => 'College of Nutrition, Hospitality and Tourism Management',
        'CME' => 'College of Maritime Education',
    ];

    public function run(): void
    {
        $departments = [
            ...array_map(fn (string $code, string $name) => [$code, $name, DepartmentType::College, self::DEAN], array_keys(self::COLLEGES), self::COLLEGES),
            [Department::REGISTRAR_CODE, 'Office of the Registrar', DepartmentType::Office, 'Verifies records, prepares the credential and does the final quality check'],
            ['VPF', 'Office of the VP for Finance', DepartmentType::Office, 'Documentary stamp (no signature)'],
            ['CHAN', 'Office of the Chancellor', DepartmentType::Office, 'Review and signature'],
            ['PRES', 'Office of the President', DepartmentType::Office, 'Signature on diplomas and certificates'],
        ];

        foreach ($departments as [$code, $name, $type, $description]) {
            Department::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'description' => $description],
            );
        }
    }
}
