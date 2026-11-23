<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

function registerForm(array $overrides = []): Testable
{
    $component = Volt::test('auth.register');

    foreach (array_merge(['email' => 'juan@example.com', 'password' => 'Password123!'], $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('register');
}

test('REQ-01-01 a guest can register with only an email and a password', function () {
    registerForm()->assertHasNoErrors()->assertRedirect(route('verification.notice', absolute: false));

    $user = User::sole();
    expect($user->email)->toBe('juan@example.com')
        ->and($user->first_name)->toBeNull()
        ->and($user->contact_number)->toBeNull()
        ->and($user->privacy_consented_at)->toBeNull()
        ->and($user->identityDocuments()->exists())->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->name)->toBe('juan@example.com');
});

test('REQ-01-02 self-registered accounts are always students', function () {
    registerForm()->assertHasNoErrors();

    expect(User::sole()->role)->toBe(Role::Student);
});

test('REQ-01-02 role and activation cannot be mass assigned', function () {
    expect(fn () => User::create([
        'first_name' => 'Eve', 'last_name' => 'Mallory', 'email' => 'eve@example.com',
        'password' => 'Password123!', 'role' => 'registrar',
    ]))->toThrow(MassAssignmentException::class);

    expect(User::count())->toBe(0);
});

test('REQ-01-03 an email already registered in any form cannot register again', function (string $typed) {
    User::factory()->create(['email' => 'juan@example.com']);

    registerForm(['email' => $typed])->assertHasErrors('email');

    expect(User::count())->toBe(1);
    $this->assertGuest();
})->with([
    'with spaces around it' => '  juan@example.com  ',
    'in capitals' => 'JUAN@Example.com',
]);

test('REQ-01-03 a second account with the same email is refused by the database too', function () {
    User::factory()->create(['email' => 'juan@example.com']);

    expect(fn () => User::factory()->create(['email' => 'juan@example.com']))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => User::factory()->create(['email' => 'JUAN@example.com']))->toThrow(UniqueConstraintViolationException::class)
        ->and(User::count())->toBe(1);
});

test('REQ-01-03 registration is rejected for a weak password', function (string $password) {
    registerForm(['password' => $password])->assertHasErrors(['password']);

    expect(User::count())->toBe(0);
})->with([
    'shorter than 8 characters' => 'Pass1!',
    'no uppercase letter' => 'password123!',
    'no lowercase letter' => 'PASSWORD123!',
    'no number' => 'Password!!',
    'no special character' => 'Password123',
]);

test('REQ-01-03 the password rules also apply when changing or resetting a password', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('updatePassword')
        ->assertHasErrors(['password']);

    Volt::test('auth.reset-password', ['token' => 'token'])
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('resetPassword')
        ->assertHasErrors(['password']);
});

test('REQ-01-04 registration emails a verification code and logs the student in', function () {
    Notification::fake();

    registerForm()->assertHasNoErrors();

    $this->assertAuthenticated();
    Notification::assertSentTo(User::sole(), VerificationCode::class, fn (VerificationCode $n) => preg_match('/^\d{6}$/', $n->code) === 1);
});
