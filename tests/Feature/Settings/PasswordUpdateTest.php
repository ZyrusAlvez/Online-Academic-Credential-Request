<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('password can be updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'New-password1')
        ->set('password_confirmation', 'New-password1')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('New-password1', $user->refresh()->password))->toBeTrue();
});

test('the current password must be correct to update it', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('settings.password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'New-password1')
        ->set('password_confirmation', 'New-password1')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});
