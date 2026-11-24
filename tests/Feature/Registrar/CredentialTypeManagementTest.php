<?php

use App\Livewire\Admin\CredentialTypes\Index;
use App\Models\CredentialType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();   // the master list is the Admin's (REQ-18-04)
});

test('REQ-03-03 the registrar can create a paid credential type with requirements and is sent to its route', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->set('form.name', 'Certificate of Units Earned')
        ->set('form.requires_payment', true)
        ->set('form.fee', '120.50')
        ->set('form.processing_days', 4)
        ->set('form.requirements', "Completed clearance\n\n  PSA-authenticated birth certificate  ")
        ->set('form.special_conditions', 'Name the receiving school.')
        ->set('form.requires_receiving_institution', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.credential-types.workflow', CredentialType::sole()));

    expect(CredentialType::sole())
        ->requires_payment->toBeTrue()
        ->fee->toBe('120.50')
        ->processing_days->toBe(4)
        ->requirements->toBe(['Completed clearance', 'PSA-authenticated birth certificate'])
        ->special_conditions->toBe('Name the receiving school.')
        ->requires_receiving_institution->toBeTrue();
});

test('REQ-03-03 fee is dropped when payment is not required and inputs are validated', function () {
    $type = CredentialType::factory()->paid('100.00')->create(['name' => 'Old']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $type->id)
        ->set('form.requires_payment', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->fee)->toBeNull();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.name', 'Old')
        ->set('form.fee', '-5')
        ->set('form.processing_days', 'ten')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique', 'form.fee' => 'min', 'form.processing_days' => 'integer']);
});

test('REQ-03-05 credential types without steps are flagged and not requestable', function () {
    $empty = CredentialType::factory()->create(['name' => 'Empty One']);
    $ready = CredentialType::factory()->withSteps(['college'])->create();
    CredentialType::factory()->inactive()->withSteps(['college'])->create();

    $this->actingAs($this->admin)->get(route('admin.credential-types'))->assertSee('No route');

    expect(CredentialType::requestable()->pluck('id')->all())->toBe([$ready->id]);
});

test('REQ-03-11 non-registrars cannot manage credential types', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $type = CredentialType::factory()->create();

    $this->actingAs($user)->get(route('admin.credential-types'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.credential-types.workflow', $type))->assertForbidden();
    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff', 'registrar']);
