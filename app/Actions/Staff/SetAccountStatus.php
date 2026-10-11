<?php

namespace App\Actions\Staff;

use App\Models\User;
use DomainException;

class SetAccountStatus
{
    public function handle(User $actor, User $account, bool $active): void
    {
        if ($account->is($actor)) {
            throw new DomainException('You cannot change the status of your own account.');
        }

        $account->forceFill(['is_active' => $active])->save();
    }
}
