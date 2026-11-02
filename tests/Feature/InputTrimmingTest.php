<?php

use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Volt\Volt;

test('text input is trimmed before it is validated and stored', function () {
    Volt::test('auth.register')
        ->set('email', '  ana@example.com ')
        ->set('password', ' secret-pass ')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::sole();
    expect($user->email)->toBe('ana@example.com')
        ->and(Hash::check(' secret-pass ', $user->password))->toBeTrue();   // passwords are kept as typed
});

test('request form text is trimmed', function () {
    $org = organisation();

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->set([
            'form.credential_type_id' => $org->free->id,
            'form.copies' => 1,
            'form.purpose' => '  Employment  ',
            'form.receiving_institution' => '  Ateneo de Manila University ',
        ])
        ->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole())
        ->purpose->toBe('Employment');
});
