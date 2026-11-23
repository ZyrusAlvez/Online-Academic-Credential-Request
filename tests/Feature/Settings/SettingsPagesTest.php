<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('REQ-01-12 nobody changes their email in settings; staff have no Profile page', function () {
    $this->actingAs(User::factory()->registrar()->create());

    $this->get('/settings/profile')->assertForbidden();
    $this->get(route('settings.password'))->assertOk()
        ->assertDontSee('Profile')
        ->assertDontSee('wire:model="email"', false)
        ->assertDontSee('First name');

    // The requester's Settings → Profile (REQ-15-07) has no email field.
    $this->actingAs(User::factory()->student()->create())->get(route('settings.profile'))->assertOk()
        ->assertDontSee('wire:model="email"', false)
        ->assertDontSee('wire:model="form.email"', false);

    expect(fn () => Volt::test('settings.profile'))->toThrow(Exception::class);
});

test('REQ-01-13 users cannot delete their own account', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.password'))
        ->assertDontSee('Delete account');

    expect(fn () => Volt::test('settings.delete-user-form'))->toThrow(Exception::class);
});
