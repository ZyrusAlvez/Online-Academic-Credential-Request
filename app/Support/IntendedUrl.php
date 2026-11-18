<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * The page a guest was sent to the login page from is honoured only when the signed-in user's
 * role can open it: a page under another role's area would answer 403 (REQ-01-33).
 */
class IntendedUrl
{
    public static function dropIfForeign(User $user): void
    {
        $intended = session('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $area = explode('/', trim((string) parse_url($intended, PHP_URL_PATH), '/'))[0];
        $areas = array_map(fn (Role $role) => $role->value, Role::cases());

        if (in_array($area, $areas, true) && $area !== $user->role->value) {
            session()->forget('url.intended');
        }
    }
}
