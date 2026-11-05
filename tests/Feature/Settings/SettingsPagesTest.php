<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('REQ-01-12 there is no settings Profile page to change a name or email', function () {
    $this->actingAs(User::factory()->registrar()->create());

    $this->get('/settings/profile')->assertNotFound();
    $this->get(route('settings.password'))->assertOk()
        ->assertDontSee('Profile')
        ->assertDontSee('wire:model="email"', false)
        ->assertDontSee('First name');

    expect(fn () => Volt::test('settings.profile'))->toThrow(Exception::class);
});

test('REQ-01-12 settings opens email notifications for requesters and the password for staff', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get('/settings')->assertRedirect(route('settings.notifications'));

    $this->actingAs(User::factory()->registrar()->create())
        ->get('/settings')->assertRedirect(route('settings.password'));

    $this->actingAs(User::factory()->departmentStaff()->create())
        ->get('/settings')->assertRedirect(route('settings.password'));
});

test('REQ-01-13 users cannot delete their own account', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.password'))
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
