<?php

use App\Livewire\Admin\Purposes\Index;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\Purpose;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();   // purposes are the Admin's (REQ-18-04)
});

test('REQ-13-01 a purpose name is unique', function () {
    Purpose::factory()->create(['name' => 'Employment']);

    expect(fn () => Purpose::factory()->create(['name' => 'Employment']))->toThrow(QueryException::class);
});

test('REQ-13-06 only registrars can open the Purposes page or change a purpose', function () {
    $purpose = Purpose::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.purposes'))->assertOk()->assertSee('Purposes');
    $this->actingAs(User::factory()->student()->create())->get(route('admin.purposes'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff()->create())->get(route('admin.purposes'))->assertForbidden();
    $this->actingAs(User::factory()->registrar()->create())->get(route('admin.purposes'))->assertForbidden();
    $this->get(route('admin.purposes'))->assertForbidden();

    Livewire::actingAs(User::factory()->student()->create())->test(Index::class)->assertForbidden();
    expect($purpose->fresh()->is_active)->toBeTrue();
});

test('REQ-13-08 only an active purpose is accepted, and its name is stored on the request', function (string $purpose) {
    $org = organisation();
    Purpose::factory()->inactive()->create(['name' => 'Retired purpose']);

    Livewire::actingAs($owner = requester($org->ccs))->test(Create::class)
        ->set('form.credential_type_id', $org->free->id)
        ->set('form.purpose', $purpose)
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasErrors(['form.purpose' => 'exists'])
        ->set('form.purpose', 'Further studies')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole()->purpose)->toBe('Further studies');
})->with(['inactive' => 'Retired purpose', 'not on the list' => 'Something I typed']);

test('REQ-13-09 renaming or deactivating a purpose keeps past requests as they were', function () {
    $org = organisation();
    $request = submitRequest(requester($org->ccs), $org->free, ['purpose' => 'Employment']);
    $purpose = Purpose::where('name', 'Employment')->firstOrFail();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $purpose->id)
        ->set('form.name', 'Local employment')
        ->call('save')
        ->call('toggleStatus', $purpose->id);

    expect($request->fresh()->purpose)->toBe('Employment');
});

test('REQ-13-11 Others asks for the purpose in a text box and stores what was typed', function () {
    $org = organisation();

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->set('form.credential_type_id', $org->free->id)
        ->assertDontSee('Please specify')
        ->set('form.purpose', CredentialRequestForm::OTHER_PURPOSE)
        ->assertSee('Please specify')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasErrors(['form.purpose_other' => 'required'])
        ->set('form.purpose_other', str_repeat('a', 501))
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasErrors(['form.purpose_other' => 'max'])
        ->set('form.purpose_other', '  Requirement for a church wedding  ')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole()->purpose)->toBe('Requirement for a church wedding');
});

test('REQ-13-11 text typed under Others is ignored once a listed purpose is chosen', function () {
    $org = organisation();

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->set('form.credential_type_id', $org->free->id)
        ->set('form.purpose', CredentialRequestForm::OTHER_PURPOSE)
        ->set('form.purpose_other', 'Something else')
        ->set('form.purpose', 'Employment')
        ->assertDontSee('Please specify')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole()->purpose)->toBe('Employment');
});
