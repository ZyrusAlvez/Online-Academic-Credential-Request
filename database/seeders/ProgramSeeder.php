<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * The 27 programs of the 2026-11-06 list, by college code (spec 12, REQ-12-12). Only adds what is
 * missing, so running it again keeps the registrar's changes.
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
        'BED' => [
            'Basic Education',
        ],
        'CBAA' => [
            'Bachelor of Science in Accountancy',
            'Bachelor of Science in Business Administration',
            'Bachelor of Science in Real Estate Management',
        ],
        'CCS' => [
            'Bachelor of Information Technology in Cybersecurity and Forensics',
            'Bachelor of Science in Computer Science, Specialization in Data Science',
            'Bachelor of Science in Entertainment and Multimedia Computing',
        ],
        'CCJE' => [
            'Bachelor of Science in Criminology',
        ],
        'CTE' => [
            'Bachelor of Early Childhood Education',
            'Bachelor of Elementary Education',
            'Bachelor of Library and Information Science',
            'Bachelor of Physical Education',
            'Bachelor of Secondary Education',
        ],
        'CEA' => [
            'Bachelor of Science in Architecture',
            'Bachelor of Science in Civil Engineering',
            'Bachelor of Science in Computer Engineering',
            'Bachelor of Science in Industrial Engineering',
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
            'Bachelor of Science in Marine Transportation',
        ],
    ];

    /**
     * Programs of the earlier list that continue under a new name, old name => new name, so
     * requesters' saved programs follow (REQ-12-12). Dropped programs are not listed.
     *
     * @var array<string, string>
     */
    public const RENAMES = [
        'BA Communication' => 'Bachelor of Arts in Communication',
        'BA Political Science' => 'Bachelor of Arts in Political Science',
        'BA/BS Psychology' => 'Bachelor of Arts/Science in Psychology',
        'BS Aircraft Maintenance and Technology' => 'Bachelor of Science in Aircraft Maintenance and Technology',
        'BS Architecture' => 'Bachelor of Science in Architecture',
        'BS Aviation Electronics Technology' => 'Bachelor of Science in Aviation Electronics Technology',
        'Basic Education (K–12)' => 'Basic Education',
        'BS Accountancy' => 'Bachelor of Science in Accountancy',
        'BS Business Administration' => 'Bachelor of Science in Business Administration',
        'BS Real Estate Management' => 'Bachelor of Science in Real Estate Management',
        'BS Computer Science, Specialization in Data Science' => 'Bachelor of Science in Computer Science, Specialization in Data Science',
        'BS Entertainment and Multimedia Computing' => 'Bachelor of Science in Entertainment and Multimedia Computing',
        'BS Information Technology in Cybersecurity and Forensics' => 'Bachelor of Information Technology in Cybersecurity and Forensics',
        'BS Criminology' => 'Bachelor of Science in Criminology',
        'BS Civil Engineering' => 'Bachelor of Science in Civil Engineering',
        'BS Computer Engineering' => 'Bachelor of Science in Computer Engineering',
        'BS Industrial Engineering' => 'Bachelor of Science in Industrial Engineering',
        "Graduate School (Master's and Doctoral Programs)" => 'Graduate School',
        'BS Hospitality Management' => 'Bachelor of Science in Hospitality Management',
        'BS Nutrition and Dietetics' => 'Bachelor of Science in Nutrition & Dietetics',
        'BS Tourism Management' => 'Bachelor of Science in Tourism Management',
        'BS Marine Transportation' => 'Bachelor of Science in Marine Transportation',
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
