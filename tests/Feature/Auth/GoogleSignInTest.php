<?php

use App\Enums\Role;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(fn () => config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']));

/** Make Socialite return this Google account on the callback. */
function googleReturns(array $claims): void
{
    $claims = array_merge(['sub' => 'google-123', 'email' => 'juan@gmail.com', 'email_verified' => true, 'given_name' => 'Juan', 'family_name' => 'Dela Cruz'], $claims);
    $user = (new GoogleUser)->setRaw($claims)->map(['id' => $claims['sub'], 'email' => $claims['email'], 'name' => trim($claims['given_name'].' '.$claims['family_name'])]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('REQ-01-24 the Google button shows on log in and sign up only when Google is configured', function () {
    $this->get(route('login'))->assertSee('Continue with Google')->assertSee(route('auth.google.redirect'), false);
    $this->get(route('register'))->assertSee('Sign up with Google');

    config(['services.google.client_id' => null]);

    $this->get(route('login'))->assertDontSee('Continue with Google');
    $this->get(route('register'))->assertDontSee('Sign up with Google');
    $this->get(route('auth.google.redirect'))->assertNotFound();
});

test('REQ-01-24 the Google button sends the user to Google', function () {
    expect($this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/o/oauth2/auth')
        ->toContain(urlencode(url('/auth/google/callback')));
});

test('REQ-01-24 a new Google account becomes a verified requester with the name pre-filled', function () {
    googleReturns([]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();
    $this->assertAuthenticatedAs($user);
    expect($user)
        ->email->toBe('juan@gmail.com')
        ->google_id->toBe('google-123')
        ->role->toBe(Role::Student)
        ->first_name->toBe('Juan')
        ->last_name->toBe('Dela Cruz')
        ->password->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('REQ-01-24 a Google sign-in with an existing email signs in to that account and links it', function () {
    $existing = User::factory()->unverified()->create(['email' => 'juan@gmail.com', 'first_name' => 'Juanito']);
    googleReturns(['email' => 'Juan@Gmail.com']);

    $this->get(route('auth.google.callback'))->assertRedirect();

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1)
        ->and($existing->fresh())->google_id->toBe('google-123')->first_name->toBe('Juanito')
        ->and($existing->fresh()->hasVerifiedEmail())->toBeTrue();

    auth()->logout();
    googleReturns(['email' => 'changed@gmail.com']);   // found again by its Google ID
    $this->get(route('auth.google.callback'));
    $this->assertAuthenticatedAs($existing);
});

test('REQ-01-24 a Google account without a verified email is refused', function () {
    googleReturns(['email_verified' => false]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('auth_error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
    $this->get(route('login'))->assertSee('no verified email address');
});

test('REQ-01-24 a deactivated account cannot sign in with Google', function () {
    User::factory()->inactive()->create(['email' => 'juan@gmail.com']);
    googleReturns([]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('auth_error', __('auth.deactivated'));

    $this->assertGuest();
    expect(User::sole()->google_id)->toBeNull();
});

test('REQ-01-24 a cancelled Google sign-in returns to log in with a message', function () {
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('auth_error');
    $this->assertGuest();
});

test('REQ-01-24 an account made with Google cannot log in with an empty password', function () {
    googleReturns([]);
    $this->get(route('auth.google.callback'));
    auth()->logout();

    expect(auth()->attempt(['email' => 'juan@gmail.com', 'password' => '']))->toBeFalse();
});
