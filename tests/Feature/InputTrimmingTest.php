<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('text input is trimmed before it is validated and stored', function () {
    Volt::test('auth.register')
        ->set('email', '  ana@example.com ')
        ->set('password', ' Secret-pass1 ')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::sole();
    expect($user->email)->toBe('ana@example.com')
        ->and(Hash::check(' Secret-pass1 ', $user->password))->toBeTrue();   // passwords are kept as typed
});
