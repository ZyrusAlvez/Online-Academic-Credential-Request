<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local and staging only. The base data and the demo accounts: a registrar, a cashier, one staff
 * account per office and four requesters, with no requests (REQ-23-07). Sample requests are DemoRequestSeeder's.
 * Password for every account: DemoSeeder::PASSWORD.
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** Every demo account's password; it meets the password rules (REQ-07-09, REQ-01-03). */
    public const PASSWORD = '@Test123';

    /** The demo requesters: email, first, middle and last name, college code, program, sex, maiden name. */
    public const REQUESTERS = [
        ['student@example.com', 'Juan', 'Santos', 'Dela Cruz', 'CCS', 'Bachelor of Science in Computer Science, Specialization in Data Science', Sex::Male, null],
        ['maria@example.com', 'Maria', null, 'Reyes', 'CBA', 'Bachelor of Science in Accountancy', Sex::Female, 'Bautista'],
        ['jose@example.com', 'Jose', 'Lopez', 'Garcia', 'CEA', 'Bachelor of Science in Civil Engineering', Sex::Male, null],
        ['ana@example.com', 'Ana', null, 'Mendoza', 'CTE', 'Bachelor of Secondary Education', Sex::Female, null],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'staging']) && ! app()->runningUnitTests()) {
            throw new RuntimeException('The demo seeder only runs in the local and staging environments.');
        }

        $this->call([DepartmentSeeder::class, ProgramSeeder::class, PurposeSeeder::class, CredentialTypeSeeder::class, AdminSeeder::class, NonWorkingDaySeeder::class]);

        self::registrarAccount();
        self::cashierAccount();

        $departments = Department::active()->get()->keyBy('code');
        $departments->each(fn (Department $department) => self::staffAccount($department));

        foreach (self::REQUESTERS as $r) {
            $this->requester($departments[$r[4]], ...array_slice($r, 0, 4), program: $r[5], sex: $r[6], maidenName: $r[7]);
        }
    }

    /**
     * The office's demo account, `<code>@example.com` with the demo password.
     */
    public static function staffAccount(Department $department): User
    {
        $user = User::firstOrNew(['email' => strtolower($department->code).'@example.com']);
        $user->fill(['first_name' => $department->code, 'middle_name' => null, 'last_name' => 'Staff', 'password' => self::PASSWORD]);
        $user->forceFill(['role' => Role::Department, 'department_id' => $department->id, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * The demo registrar, `registrar@example.com` with the demo password, who works the demo
     * requests (REQ-18-12). There is no demo admin: the first admin is the one from
     * `INITIAL_ADMIN_*`.
     */
    public static function registrarAccount(): User
    {
        $user = User::firstOrNew(['email' => 'registrar@example.com']);
        $user->fill(['first_name' => 'Demo', 'middle_name' => null, 'last_name' => 'Registrar', 'password' => self::PASSWORD]);
        $user->forceFill(['role' => Role::Registrar, 'department_id' => null, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * The demo cashier, `cashier@example.com` with the demo password, who validates the demo
     * requests' online payments (REQ-25-10).
     */
    public static function cashierAccount(): User
    {
        $user = User::firstOrNew(['email' => 'cashier@example.com']);
        $user->fill(['first_name' => 'Demo', 'middle_name' => null, 'last_name' => 'Cashier', 'password' => self::PASSWORD]);
        $user->forceFill(['role' => Role::Cashier, 'department_id' => null, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    private function requester(Department $college, string $email, string $first, ?string $middle, string $last, string $program, Sex $sex, ?string $maidenName): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'sex' => $sex,
            'maiden_name' => $maidenName,
            'password' => self::PASSWORD,
            'contact_number' => '0917'.fake()->numerify('#######'),
            'academic_status' => AcademicStatus::Graduate,
            'college_id' => $college->id,
            'program' => $program,
            'student_number' => sprintf('%02d-%04d-%03d', fake()->numberBetween(15, 20), fake()->numberBetween(1, 9999), fake()->numberBetween(1, 999)),
            'attended_from' => 2020,
            'attended_to' => 2024,
        ]);
        $user->forceFill(['role' => Role::Student, 'is_active' => true, 'email_verified_at' => now(), 'privacy_consented_at' => now()])->save();

        return $user;
    }
}
