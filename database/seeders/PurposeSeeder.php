<?php

namespace Database\Seeders;

use App\Models\Purpose;
use Illuminate\Database\Seeder;

/**
 * The starter list of request purposes (spec 13). Only adds what is missing, so running it again
 * keeps the registrar's changes.
 */
class PurposeSeeder extends Seeder
{
    /** @var list<string> */
    public const PURPOSES = [
        'Board or licensure examination',
        'Employment',
        'Employment abroad',
        'Further studies',
        'Personal copy',
        'Scholarship application',
        'Transfer to another school',
        'Visa or immigration',
    ];

    public function run(): void
    {
        foreach (self::PURPOSES as $name) {
            Purpose::firstOrCreate(['name' => $name]);
        }
    }
}
