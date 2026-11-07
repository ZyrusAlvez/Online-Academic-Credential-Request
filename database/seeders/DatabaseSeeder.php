<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            ProgramSeeder::class,
            PurposeSeeder::class,
            CredentialTypeSeeder::class,
            RegistrarSeeder::class,
            AdminSeeder::class,
            NonWorkingDaySeeder::class,
        ]);
    }
}
