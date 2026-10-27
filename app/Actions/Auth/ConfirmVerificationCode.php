<?php

namespace App\Actions\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConfirmVerificationCode
{
    /** Wrong guesses allowed per code before a new one must be requested. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Mark the user's email as verified when the code matches (REQ-01-23).
     *
     * @throws ValidationException when the code is missing, expired, used up or wrong
     */
    public function handle(User $user, string $code): void
    {
        // The error is thrown after the transaction so that a wrong guess still counts.
        $error = DB::transaction(function () use ($user, $code): ?string {
            $record = EmailVerificationCode::where('user_id', $user->id)->lockForUpdate()->first();

            if ($record === null || $record->expires_at->isPast()) {
                return 'This code has expired. Ask for a new one.';
            }

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                return 'Too many wrong codes. Ask for a new one.';
            }

            if (! Hash::check($code, $record->code_hash)) {
                $record->increment('attempts');

                return $record->attempts >= self::MAX_ATTEMPTS
                    ? 'Too many wrong codes. Ask for a new one.'
                    : 'That code is not correct.';
            }

            $record->delete();
            $user->markEmailAsVerified();

            return null;
        });

        if ($error !== null) {
            throw self::fail($error);
        }

        event(new Verified($user));
    }

    private static function fail(string $message): ValidationException
    {
        return ValidationException::withMessages(['code' => $message]);
    }
}
