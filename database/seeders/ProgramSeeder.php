<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * The 34 programs of the list, by college code (spec 12, REQ-12-02): the 2026-11-06 list, with
 * 7 programs added on 2026-11-15 and Senior High School's STEM strand on 2026-11-18 (REQ-28-09).
 * Only adds what is missing, so running
 * it again keeps the Admin's changes. Requires DepartmentSeeder.
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
        'SOA' => [
            'Bachelor of Science in Aircraft Maintenance and Technology',
            'Bachelor of Science in Aviation Electronics Technology',
        ],
        // Senior High School's programs are its strands: STEM until the Registrar's list (REQ-28-09).
        'SHS' => [
            'Science, Technology, Engineering and Mathematics (STEM)',
        ],
        'CBA' => [
            'Bachelor of Science in Accountancy',
            'Bachelor of Science in Business Administration',
            'Bachelor of Science in Management Accounting',
            'Bachelor of Science in Real Estate Management',
        ],
        'CCS' => [
            'Bachelor of Information Technology in Cybersecurity and Forensics',
            'Bachelor of Science in Computer Science, Specialization in Data Science',
            'Bachelor of Science in Entertainment and Multimedia Computing',
            'Bachelor of Science in Information Technology, Specialization in Game Development',
        ],
        'COC' => [
            'Bachelor of Science in Criminology',
        ],
        'CTE' => [
            'Bachelor of Early Childhood Education',
            'Bachelor of Elementary Education',
            'Bachelor of Library and Information Science',
            'Bachelor of Physical Education',
            'Bachelor of Secondary Education',
            'Bachelor of Special Needs Education',
        ],
        'CEA' => [
            'Bachelor of Science in Architecture',
            'Bachelor of Science in Civil Engineering',
            'Bachelor of Science in Computer Engineering',
            'Bachelor of Science in Electrical Engineering',
            'Bachelor of Science in Electronics Engineering',
            'Bachelor of Science in Industrial Engineering',
            'Bachelor of Science in Mechanical Engineering',
        ],
        'GS' => [
            'Graduate School',
        ],
        'CNHTM' => [
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Nutrition & Dietetics',
            'Bachelor of Science in Tourism Management',
        ],
        'CME' => [
            'Bachelor of Science in Marine Engineering',
            'Bachelor of Science in Marine Transportation',
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
