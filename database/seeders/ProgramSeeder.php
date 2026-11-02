<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * The programs published at https://uphsl.edu.ph/academic-programs/, by college code (spec 12).
 * Only adds what is missing, so running it again keeps the registrar's changes.
 */
class ProgramSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const PROGRAMS = [
        'CAS' => [
            'Bachelor of Arts in Communication',
            'Bachelor of Arts in Political Science',
            'Bachelor of Arts/Science in Psychology',
        ],
        'CBA' => [
            'Bachelor of Science in Accountancy',
        ],
        'CCS' => [
            'Bachelor of Information Technology in Cybersecurity and Forensics',
        ],
        'COED' => [
            'Bachelor of Early Childhood Education',
            'Bachelor of Elementary Education',
            'Bachelor of Library and Information Science',
            'Bachelor of Physical Education',
        ],
        'CAA' => [
            'Bachelor of Science in Aircraft Maintenance and Technology',
            'Bachelor of Science in Aviation Electronics Technology',
        ],
    ];

    public function run(): void
    {
        $colleges = Department::colleges()->whereIn('code', array_keys(self::PROGRAMS))->pluck('id', 'code');

        foreach (self::PROGRAMS as $code => $names) {
            foreach ($names as $name) {
                if (isset($colleges[$code])) {
                    Program::firstOrCreate(['department_id' => $colleges[$code], 'name' => $name]);
                }
            }
        }
    }
}
