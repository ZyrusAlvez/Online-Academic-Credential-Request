<?php

namespace App\Actions\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SendVerificationCode
{
    /** A new code can be requested once this many seconds have passed since the last one. */
    public const COOLDOWN_SECONDS = 30;

    public const EXPIRES_MINUTES = 10;

    /**
     * Email the user a new six-digit code, replacing any earlier one (REQ-01-04, REQ-01-23).
     *
     * @throws ValidationException when the last code was sent less than 30 seconds ago
     */
    public function handle(User $user): void
    {
        $key = self::throttleKey($user);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages([
                'code' => 'Please wait '.RateLimiter::availableIn($key).' seconds before asking for another code.',
            ]);
        }

        RateLimiter::hit($key, self::COOLDOWN_SECONDS);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::updateOrCreate(['user_id' => $user->id], [
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'attempts' => 0,
        ]);

        $user->notify(new VerificationCode($code));
    }

    /**
     * Seconds until the user may ask for another code; 0 when they may now.
     */
    public static function cooldown(User $user): int
    {
        $key = self::throttleKey($user);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    private static function throttleKey(User $user): string
    {
        return 'verification-code:'.$user->id;
    }
}
