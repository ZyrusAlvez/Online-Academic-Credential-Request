<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
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
        'password' => 'password123',
        'password_confirmation' => 'password123',
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

test('REQ-01-01 a guest can register with first, middle and last name', function () {
    registerForm()->assertHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();
    expect($user->first_name)->toBe('Juan')
        ->and($user->middle_name)->toBe('Dizon')
        ->and($user->last_name)->toBe('Dela Cruz');
});

test('REQ-01-01 middle name is optional', function () {
    registerForm(['middle_name' => ''])->assertHasNoErrors();

    expect(User::sole()->middle_name)->toBeNull();
});

test('REQ-01-02 self-registered accounts are always students', function () {
    registerForm()->assertHasNoErrors();

    expect(User::sole()->role)->toBe(Role::Student);
});

test('REQ-01-02 role cannot be mass assigned', function () {
    $user = User::create([
        'first_name' => 'Eve', 'last_name' => 'Mallory', 'email' => 'eve@example.com',
        'password' => 'password123', 'role' => 'registrar', 'is_active' => false,
    ]);

    expect($user->fresh()->role)->toBe(Role::Student)
        ->and($user->fresh()->is_active)->toBeTrue();
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

test('REQ-01-04 registration sends a verification email and logs the student in', function () {
    Notification::fake();

    registerForm()->assertHasNoErrors();

    $this->assertAuthenticated();
    Notification::assertSentTo(User::sole(), VerifyEmail::class);
});
