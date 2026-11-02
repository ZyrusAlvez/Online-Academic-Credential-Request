<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * UPHSL's 34 published programs, by the college codes of the client's office list (spec 12). The
 * site names some colleges differently; Architecture sits with Aviation here, as on that list.
 * Only adds what is missing, so running it again keeps the registrar's changes.
 */
class ProgramSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const PROGRAMS = [
        'CAS' => [
            'BA Communication',
            'BA Political Science',
            'BA/BS Psychology',
        ],
        'CAA' => [
            'BS Aircraft Maintenance and Technology',
            'BS Architecture',
            'BS Aviation Electronics Technology',
        ],
        'BED' => [
            'Basic Education (K–12)',
        ],
        'CBA' => [
            'BS Accountancy',
            'BS Business Administration',
            'BS Management Accounting',
            'BS Real Estate Management',
        ],
        'CCS' => [
            'BS Computer Science, Specialization in Data Science',
            'BS Entertainment and Multimedia Computing',
            'BS Information Technology in Cybersecurity and Forensics',
            'BS Information Technology, Specialization in Game Development',
        ],
        'CRIM' => [
            'BS Criminology',
        ],
        'COED' => [
            'Bachelor of Early Childhood Education',
            'Bachelor of Elementary Education',
            'Bachelor of Library and Information Science',
            'Bachelor of Physical Education',
            'Bachelor of Secondary Education',
            'Bachelor of Special Needs Education',
        ],
        'COE' => [
            'BS Civil Engineering',
            'BS Computer Engineering',
            'BS Electrical Engineering',
            'BS Electronics Engineering',
            'BS Industrial Engineering',
            'BS Mechanical Engineering',
        ],
        'GS' => [
            "Graduate School (Master's and Doctoral Programs)",
        ],
        'CIHM' => [
            'BS Hospitality Management',
            'BS Nutrition and Dietetics',
            'BS Tourism Management',
        ],
        'CME' => [
            'BS Marine Engineering',
            'BS Marine Transportation',
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
