<?php

use App\Livewire\Registrar\Departments\Index as Departments;
use App\Livewire\Registrar\Programs\Index as Programs;
use App\Livewire\Registrar\Purposes\Index as Purposes;
use App\Livewire\Registrar\Requests\Index as Requests;
use App\Livewire\Registrar\Users\Index as Users;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
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

test('request form text is trimmed', function () {
    $org = organisation();

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->set([
            'form.credential_type_id' => $org->free->id,
            'form.purpose' => '  Employment  ',
            'form.receiving_institution' => '  Ateneo de Manila University ',
        ])
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole())
        ->purpose->toBe('Employment');
});

test('REQ-09-17 a search box keeps the space typed between words', function (string $component) {
    Livewire::actingAs(User::factory()->registrar()->create())->test($component)
        ->set('search', 'sad ')
        ->assertSet('search', 'sad ');
})->with([
    'accounts' => [Users::class],
    'requests' => [Requests::class],
    'offices' => [Departments::class],
    'programs' => [Programs::class],
    'purposes' => [Purposes::class],
]);

test('REQ-09-17 the search ignores the spaces around the words', function () {
    User::factory()->student()->create(['first_name' => 'Sad', 'middle_name' => null, 'last_name' => 'Girl', 'email' => 'sg@example.com']);
    User::factory()->student()->create(['first_name' => 'Sam', 'middle_name' => null, 'last_name' => 'Student', 'email' => 'sam@example.com']);

    Livewire::actingAs(User::factory()->registrar()->create(['first_name' => 'Rita', 'last_name' => 'Registrar']))->test(Users::class)
        ->set('search', ' sad ')
        ->assertSee('Sad Girl')->assertDontSee('Sam Student')
        ->set('search', 'sad girl ')
        ->assertSee('Sad Girl')->assertDontSee('Sam Student');
});
