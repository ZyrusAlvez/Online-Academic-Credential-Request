<?php

namespace App\Listeners;

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

/**
 * Logs sign-ins, sign-outs and failed sign-ins (REQ-14-05). Google sign-in goes through
 * Auth::login, so it fires Login too.
 */
class RecordSignInActivity
{
    public function __construct(private RecordActivity $record) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // A remember-me cookie signs a deactivated account in for a moment before
        // EnsureUserIsActive throws it out; that is a refusal, not a sign-in.
        if (! $event->user->is_active) {
            $this->record->handle($event->user, ActivityCategory::SignIn, 'sign_in.refused', 'Sign-in refused: the account is deactivated', subject: $event->user);

            return;
        }

        $this->record->handle(
            $event->user,
            ActivityCategory::SignIn,
            'sign_in.login',
            $event->remember ? 'Signed in (remember me)' : 'Signed in',
            subject: $event->user,
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->record->handle(
            $event->user,
            ActivityCategory::SignIn,
            'sign_in.logout',
            $event->user->is_active ? 'Signed out' : 'Signed out: the account is deactivated',
            subject: $event->user,
        );
    }

    /** Nobody is signed in, so there is no actor; the account, if the email matched one, is the subject. */
    public function handleFailed(Failed $event): void
    {
        $email = Str::limit((string) ($event->credentials['email'] ?? ''), 255, '');

        $this->record->handle(
            null,
            ActivityCategory::SignIn,
            'sign_in.failed',
            "Failed sign-in for {$email}",
            subject: $event->user instanceof User ? $event->user : null,
        );
    }
}
