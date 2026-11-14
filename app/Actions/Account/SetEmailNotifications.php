<?php

namespace App\Actions\Account;

use App\Models\User;

/**
 * A requester turns their email notifications on or off (REQ-06-16). In-app notifications are
 * unaffected.
 */
class SetEmailNotifications
{
    public function handle(User $user, bool $enabled): void
    {
        $user->forceFill(['email_notifications' => $enabled])->save();
    }
}
