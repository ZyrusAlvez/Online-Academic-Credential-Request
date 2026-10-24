<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The active accounts that act for an office: its department staff, and registrar accounts for
 * the Office of the Registrar.
 */
class OfficeStaff
{
    /**
     * @return Collection<int, User>
     */
    public static function of(Department $office): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('role', Role::Department)->where('department_id', $office->id))
                ->when($office->code === Department::REGISTRAR_CODE, fn ($q) => $q->orWhere('role', Role::Registrar)))
            ->get();
    }
}
