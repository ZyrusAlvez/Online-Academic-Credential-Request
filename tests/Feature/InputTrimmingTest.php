<?php

use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Volt\Volt;

test('text input is trimmed before it is validated and stored', function () {
    Volt::test('auth.register')
        ->set('first_name', '  Ana ')
        ->set('middle_name', '   ')
        ->set('last_name', ' Cruz  ')
        ->set('email', '  ana@example.com ')
        ->set('password', ' secret-pass ')
        ->set('password_confirmation', ' secret-pass ')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::sole();
    expect($user->first_name)->toBe('Ana')
        ->and($user->middle_name)->toBeNull()
        ->and($user->last_name)->toBe('Cruz')
        ->and($user->email)->toBe('ana@example.com')
        ->and(Hash::check(' secret-pass ', $user->password))->toBeTrue();   // passwords are kept as typed
});

test('request form text is trimmed', function () {
    $org = organisation();

    Livewire::actingAs(User::factory()->student()->create())->test(Create::class)
        ->set([
            'form.credential_type_id' => $org->free->id,
            'form.copies' => 1,
            'form.purpose' => '  Employment  ',
            'form.student_number' => ' 19-0001-123 ',
            'form.college_id' => $org->ccs->id,
            'form.program' => ' BS Computer Science ',
            'form.student_status' => 'alumni',
            'form.last_school_year' => ' 2023-2024 ',
        ])
        ->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole())
        ->purpose->toBe('Employment')
        ->student_number->toBe('19-0001-123')
        ->program->toBe('BS Computer Science')
        ->last_school_year->toBe('2023-2024');
});
