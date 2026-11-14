<?php

namespace App\Livewire\Concerns;

use App\Actions\Identity\RemoveSavedIdentityDocument;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The saved ID's "Remove" button on New Request, Edit Request and Settings → Profile (REQ-15-18).
 * It only ever acts on the signed-in requester's own saved ID.
 */
trait RemovesSavedId
{
    public function removeSavedId(RemoveSavedIdentityDocument $remove): void
    {
        /** @var User $user */
        $user = Auth::user();

        $remove->handle($user);

        $this->dispatch('notify', message: 'Your saved ID was removed. Upload a valid ID to use instead.');
    }
}
