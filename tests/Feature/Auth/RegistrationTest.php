<?php

use App\Enums\IdentityStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

function registerForm(array $overrides = []): Testable
{
    $data = array_merge([
        'first_name' => 'Juan',
        'middle_name' => 'Dizon',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'contact_number' => '0917 123 4567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'privacy_consent' => true,
    ], $overrides);

    $component = Volt::test('auth.register');

    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('register');
}

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk()->assertSee('First name')->assertSee('Middle name');
});

test('REQ-01-01 a guest can register with names, contact number and privacy consent', function () {
    registerForm()->assertHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();
    expect($user->first_name)->toBe('Juan')
        ->and($user->middle_name)->toBe('Dizon')
        ->and($user->last_name)->toBe('Dela Cruz')
        ->and($user->contact_number)->toBe('0917 123 4567')
        ->and($user->privacy_consented_at)->not->toBeNull()
        ->and($user->identity_status)->toBe(IdentityStatus::Unsubmitted);
});

test('REQ-01-01 middle name is optional', function () {
    registerForm(['middle_name' => ''])->assertHasNoErrors();

    expect(User::sole()->middle_name)->toBeNull();
});

test('REQ-01-02 self-registered accounts are always students', function () {
    registerForm()->assertHasNoErrors();

    expect(User::sole()->role)->toBe(Role::Student);
});

test('REQ-01-02 role and activation cannot be mass assigned', function () {
    expect(fn () => User::create([
        'first_name' => 'Eve', 'last_name' => 'Mallory', 'email' => 'eve@example.com',
        'password' => 'password123', 'role' => 'registrar',
    ]))->toThrow(MassAssignmentException::class);

    expect(User::count())->toBe(0);
});

test('REQ-01-03 registration is rejected for a duplicate email', function () {
    User::factory()->create(['email' => 'juan@example.com']);

    registerForm()->assertHasErrors(['email' => 'unique']);
});

test('REQ-01-03 registration is rejected when the password is unconfirmed or too short', function () {
    registerForm(['password_confirmation' => 'different123'])->assertHasErrors(['password']);
    registerForm(['password' => 'short', 'password_confirmation' => 'short'])->assertHasErrors(['password']);

    expect(User::count())->toBe(0);
});

test('REQ-01-03 registration is rejected without consent or with an invalid contact number', function () {
    registerForm(['privacy_consent' => false])->assertHasErrors(['privacy_consent' => 'accepted']);
    registerForm(['contact_number' => 'call me'])->assertHasErrors(['contact_number' => 'regex']);
    registerForm(['contact_number' => ''])->assertHasErrors(['contact_number' => 'required']);

    expect(User::count())->toBe(0);
});

test('REQ-01-04 registration sends a verification email and logs the student in', function () {
    Notification::fake();

    registerForm()->assertHasNoErrors();

    $this->assertAuthenticated();
    Notification::assertSentTo(User::sole(), VerifyEmail::class);
});
