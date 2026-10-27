<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignInWithGoogle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Sign in or sign up with a Google account (REQ-01-24).
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(SignInWithGoogle $signIn): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->failed('Google sign-in was cancelled or did not complete. Please try again.');
        }

        try {
            $user = $signIn->handle($google);
        } catch (ValidationException $e) {
            return $this->failed($e->validator->errors()->first());
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('auth_error', $message);
    }
}
