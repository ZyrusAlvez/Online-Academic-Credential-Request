<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as GoogleUser;

class SignInWithGoogle
{
    /**
     * Find, link or create the account for a Google sign-in (REQ-01-24). Google has verified the
     * address, so the email counts as verified; a new account is a requester with the name
     * pre-filled for My Profile.
     *
     * @throws ValidationException when Google has not verified the email or the account is inactive
     */
    public function handle(GoogleUser $google): User
    {
        $email = strtolower(trim((string) $google->getEmail()));
        $claims = $google instanceof AbstractUser ? $google->getRaw() : [];

        if ($email === '' || ($claims['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['email' => 'Your Google account has no verified email address.']);
        }

        return DB::transaction(function () use ($google, $email, $claims): User {
            $user = User::where('google_id', $google->getId())->first()
                ?? User::where('email', $email)->lockForUpdate()->first();

            if ($user !== null && ! $user->is_active) {
                throw ValidationException::withMessages(['email' => __('auth.deactivated')]);
            }

            if ($user === null) {
                // Self-registered accounts are always students; role is not mass assignable.
                $user = User::create([
                    'email' => $email,
                    'first_name' => self::name($claims['given_name'] ?? null),
                    'last_name' => self::name($claims['family_name'] ?? null),
                ]);
            }

            $user->forceFill(['google_id' => (string) $google->getId()]);

            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()]);
            }

            $user->save();

            return $user;
        });
    }

    private static function name(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 100) : null;
    }
}
