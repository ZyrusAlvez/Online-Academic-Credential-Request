<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get('/settings/profile')->assertOk();
});

test('REQ-01-12 staff can update their name', function () {
    $user = User::factory()->registrar()->create();
    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('first_name', 'Maria')
        ->set('middle_name', '')
        ->set('last_name', 'Santos')
        ->set('email', $user->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->first_name)->toBe('Maria')
        ->and($user->middle_name)->toBeNull()
        ->and($user->last_name)->toBe('Santos')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('REQ-01-12 changing the email resets verification', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('email', 'new@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->toBeNull();
});

test('REQ-01-13 users cannot delete their own account', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/profile')
        ->assertDontSee('Delete account');

    expect(fn () => Volt::test('settings.delete-user-form'))->toThrow(Exception::class);
});

test('REQ-01-14 names are displayed as First M. Last', function () {
    $withMiddle = User::factory()->make(['first_name' => 'Juan', 'middle_name' => 'dizon', 'last_name' => 'Dela Cruz']);
    $withoutMiddle = User::factory()->make(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Reyes']);

    expect($withMiddle->name)->toBe('Juan D. Dela Cruz')
        ->and($withoutMiddle->name)->toBe('Ana Reyes')
        ->and($withMiddle->initials())->toBe('JD');
});

test('REQ-01-22 requesters edit their name in My Profile, not in settings', function () {
    $user = User::factory()->student()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $this->actingAs($user);

    $this->get('/settings/profile')->assertOk()->assertDontSee('First name')->assertSee('My Profile');

    Volt::test('settings.profile')
        ->set('first_name', 'Pedro')
        ->set('email', 'juan.cruz@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh())->first_name->toBe('Juan')->email->toBe('juan.cruz@example.com');
});
