<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replaces the earlier college and program list with the 2026-11-06 one on an existing database
 * (REQ-02-14, REQ-12-12). Colleges are renamed in place, because requests, requesters and office
 * visits hold them with RESTRICT keys. Programs and staff accounts are replaced. Safe to run
 * again: a fresh install already has the new list and only gets its programs re-created.
 */
class ReplaceCollegeListSeeder extends Seeder
{
    /** Earlier college code => its successor in DepartmentSeeder::COLLEGES. */
    public const SUCCESSORS = [
        'CAS' => 'CAS',
        'CAA' => 'SOA',
        'BED' => 'BED',
        'CBA' => 'CBAA',
        'CCS' => 'CCS',
        'CRIM' => 'CCJE',
        'COED' => 'CTE',
        'COE' => 'CEA',
        'GS' => 'GS',
        'CIHM' => 'CNHTM',
        'CME' => 'CME',
    ];

    private const DEMO_DOMAIN = '@example.com';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->renameColleges();
            $colleges = $this->ensureColleges();

            $this->moveArchitectureRequesters($colleges);
            $this->replacePrograms();
            $this->replaceStaff($colleges);
            $this->removeOtherColleges();

            if (app()->environment(['local', 'staging'])) {
                $colleges->each(fn (Department $college) => DemoSeeder::staffAccount($college));
            }
        });
    }

    /**
     * Each earlier college becomes its successor, keeping its id and so every request, requester,
     * route step and office visit that names it.
     */
    private function renameColleges(): void
    {
        foreach (self::SUCCESSORS as $old => $new) {
            $college = Department::where('code', $old)->first();

            if ($college === null || ($old !== $new && Department::where('code', $new)->exists())) {
                continue;
            }

            Department::whereKey($college->id)->update(['code' => $new]);
        }
    }

    /**
     * @return Collection<string, Department> code => college
     */
    private function ensureColleges(): Collection
    {
        foreach (DepartmentSeeder::COLLEGES as $code => $name) {
            $college = Department::firstOrNew(['code' => $code]);
            $college->fill(['name' => $name, 'type' => DepartmentType::College]);
            $college->description ??= DepartmentSeeder::DEAN;
            $college->is_active = true;
            $college->save();
        }

        return Department::whereIn('code', array_keys(DepartmentSeeder::COLLEGES))->get()->keyBy('code');
    }

    /**
     * Architecture moved from the earlier Architecture and Aviation to Engineering and Architecture.
     *
     * @param  Collection<string, Department>  $colleges
     */
    private function moveArchitectureRequesters(Collection $colleges): void
    {
        User::where('college_id', $colleges['SOA']->id)
            ->whereIn('program', ['BS Architecture', 'Bachelor of Science in Architecture'])
            ->update(['college_id' => $colleges['CEA']->id]);
    }

    /**
     * Programs are only a pick list (profiles and requests hold the name as text), so the earlier
     * rows go; saved programs that continue take their new name.
     */
    private function replacePrograms(): void
    {
        Program::query()->delete();
        (new ProgramSeeder)->run();

        foreach (ProgramSeeder::RENAMES as $old => $new) {
            User::where('program', $old)->update(['program' => $new]);
        }
    }

    /**
     * The earlier colleges' staff accounts are removed; one that has acted is deactivated instead.
     * In local and staging the demo accounts are kept and moved to the new code's address.
     *
     * @param  Collection<string, Department>  $colleges
     */
    private function replaceStaff(Collection $colleges): void
    {
        $collegeIds = Department::where('type', DepartmentType::College)->pluck('code', 'id');
        $demo = app()->environment(['local', 'staging']);

        User::where('role', Role::Department)->whereIn('department_id', $collegeIds->keys())->get()
            ->each(function (User $staff) use ($collegeIds, $colleges, $demo): void {
                $code = $collegeIds[$staff->department_id];
                $address = strtolower($code).self::DEMO_DOMAIN;

                $keep = $demo && $colleges->has($code) && Str::endsWith($staff->email, self::DEMO_DOMAIN)
                    && ($staff->email === $address || ! User::where('email', $address)->exists());

                if ($keep) {
                    User::whereKey($staff->id)->update(['email' => $address, 'first_name' => $code]);

                    return;
                }

                $this->removeOrDeactivate($staff);
            });
    }

    /**
     * Colleges outside the new list (the College of Law) are deleted with everything that names
     * them (REQ-02-18).
     */
    private function removeOtherColleges(): void
    {
        (new RemoveCollegeOfLawSeeder)->run();
    }

    /**
     * Deletes in a savepoint; a RESTRICT key (history) rolls it back and the account is deactivated.
     */
    private function removeOrDeactivate(User $staff): void
    {
        try {
            DB::transaction(function () use ($staff): void {
                $staff->notifications()->delete();
                $staff->delete();
            });
        } catch (QueryException) {
            User::whereKey($staff->id)->update(['is_active' => false]);
        }
    }
}
