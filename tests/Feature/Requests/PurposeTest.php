<?php

use App\Livewire\Admin\Purposes\Index;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Student\Requests\Create;
use App\Models\CredentialRequest;
use App\Models\Purpose;
use App\Models\User;
use Database\Seeders\PurposeSeeder;
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

test('REQ-13-02 the seeder installs the starter list without duplicates', function () {
    $this->seed(PurposeSeeder::class);
    $this->seed(PurposeSeeder::class);

    expect(Purpose::orderBy('name')->pluck('name')->all())->toBe(PurposeSeeder::PURPOSES);
});

test('REQ-13-02 seeding again keeps what the registrar changed', function () {
    $purpose = Purpose::factory()->inactive()->create(['name' => PurposeSeeder::PURPOSES[0]]);
    Purpose::factory()->create(['name' => 'Renamed from Employment']);

    $this->seed(PurposeSeeder::class);

    expect($purpose->fresh()->is_active)->toBeFalse()
        ->and(Purpose::count())->toBe(count(PurposeSeeder::PURPOSES) + 1);
});

test('REQ-13-03 the registrar can search and filter purposes', function () {
    Purpose::factory()->create(['name' => 'Employment abroad']);
    Purpose::factory()->inactive()->create(['name' => 'Retired purpose']);
    Purpose::factory()->create(['name' => 'Scholarship application']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Employment abroad')
        ->assertSee('Scholarship application')
        ->set('search', 'abroad')
        ->assertSee('Employment abroad')
        ->assertDontSee('Scholarship application')
        ->set('search', '')
        ->set('status', 'inactive')
        ->assertSee('Retired purpose')
        ->assertDontSee('Employment abroad');
});

test('REQ-13-04 the registrar can add a purpose and edit it', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->assertDispatched('modal-show', name: 'purpose-form')
        ->set('form.name', 'Visa application')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $purpose = Purpose::where('name', 'Visa application')->firstOrFail();
    expect($purpose->is_active)->toBeTrue();

    $component->call('edit', $purpose->id)
        ->assertSet('form.name', 'Visa application')
        ->set('form.name', 'Visa or immigration')
        ->call('save')
        ->assertHasNoErrors();

    expect($purpose->fresh()->name)->toBe('Visa or immigration');
});

test('REQ-13-04 a purpose needs a name with no twin', function () {
    Purpose::factory()->create(['name' => 'Employment']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('create')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required'])
        ->set('form.name', 'Employment')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique']);

    expect(Purpose::count())->toBe(1);
});

test('REQ-13-05 the registrar deactivates and reactivates a purpose; inactive ones are not offered', function () {
    $org = organisation();
    $purpose = Purpose::where('name', 'Employment')->firstOrFail();

    $owner = requester($org->ccs);

    Livewire::actingAs($this->admin)->test(Index::class)->call('toggleStatus', $purpose->id);
    expect($purpose->fresh()->is_active)->toBeFalse();
    Livewire::actingAs($owner)->test(Create::class)
        ->assertViewHas('purposes', fn ($purposes) => ! $purposes->contains('Employment'));

    Livewire::actingAs($this->admin)->test(Index::class)->call('toggleStatus', $purpose->id);
    expect($purpose->fresh()->is_active)->toBeTrue();
    Livewire::actingAs($owner)->test(Create::class)
        ->assertViewHas('purposes', fn ($purposes) => $purposes->contains('Employment'));
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

test('REQ-13-07 New Request offers the active purposes in alphabetical order', function () {
    $org = organisation();
    Purpose::factory()->inactive()->create(['name' => 'Aardvark studies']);

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->assertViewHas('purposes', fn ($purposes) => $purposes->all() === PurposeSeeder::PURPOSES)
        ->assertSeeInOrder(['Board or licensure examination', 'Employment', 'Visa or immigration', 'Others (please specify)'])
        ->assertDontSee('Aardvark studies');
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

test('REQ-13-10 with no active purpose the requester can still choose Others', function () {
    $org = organisation();
    Purpose::query()->update(['is_active' => false]);

    Livewire::actingAs(requester($org->ccs))->test(Create::class)
        ->assertViewHas('purposes', fn ($purposes) => $purposes->isEmpty())
        ->assertSee('Others (please specify)')
        ->set('form.credential_type_id', $org->free->id)
        ->set('form.purpose', CredentialRequestForm::OTHER_PURPOSE)
        ->set('form.purpose_other', 'Embassy requirement')
        ->set('profile.privacy_consent', true)->call('review')->call('submit')
        ->assertHasNoErrors();

    expect(CredentialRequest::sole()->purpose)->toBe('Embassy requirement');
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
