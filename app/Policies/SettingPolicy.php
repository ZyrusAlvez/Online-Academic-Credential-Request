<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;

/**
 * System-wide settings are the Admin's (REQ-36-12).
 */
class SettingPolicy
{
    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
