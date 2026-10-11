<?php

use App\Livewire\Registrar\CredentialTypes\Index;
use App\Models\CredentialType;
use App\Models\User;
use Database\Seeders\CredentialTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-03-01 the seeder creates default credential types with workflows', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);

    $tor = CredentialType::where('name', 'Transcript of Records')->sole();
    expect(CredentialType::count())->toBe(7)
        ->and($tor->requires_payment)->toBeTrue()
        ->and($tor->steps()->with('department')->get()->map->label()->all())
        ->toBe(['Library', 'Cashier / Finance Office', "Student's College", 'Office of the Registrar']);
});

test('REQ-03-01 re-seeding does not duplicate types or overwrite edited workflows', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);
    $tor = CredentialType::where('name', 'Transcript of Records')->sole();
    $tor->steps()->first()->delete();

    $this->seed(CredentialTypeSeeder::class);

    expect(CredentialType::count())->toBe(7)
        ->and($tor->steps()->count())->toBe(3);
});

test('REQ-03-02 the registrar sees credential types with payment, processing and step count', function () {
    CredentialType::factory()->paid('250.00')->withSteps(['college'])->create(['name' => 'Transcript of Records', 'processing_days' => 10]);

    $this->actingAs($this->registrar)->get(route('registrar.credential-types'))
        ->assertOk()
        ->assertSee('Transcript of Records')
        ->assertSee('₱250.00')
        ->assertSee('10 working days')
        ->assertSee('1 step');
});

test('REQ-03-03 the registrar can create a paid credential type and is sent to its workflow', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('create')
        ->set('form.name', 'Certificate of Units Earned')
        ->set('form.requires_payment', true)
        ->set('form.fee', '120.50')
        ->set('form.processing_days', 4)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('registrar.credential-types.workflow', CredentialType::sole()));

    expect(CredentialType::sole())
        ->requires_payment->toBeTrue()
        ->fee->toBe('120.50')
        ->processing_days->toBe(4);
});

test('REQ-03-03 fee is dropped when payment is not required and inputs are validated', function () {
    $type = CredentialType::factory()->paid('100.00')->create(['name' => 'Old']);

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->call('edit', $type->id)
        ->set('form.requires_payment', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->fee)->toBeNull();

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('form.name', 'Old')
        ->set('form.fee', '-5')
        ->set('form.processing_days', 'ten')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique', 'form.fee' => 'min', 'form.processing_days' => 'integer']);
});

test('REQ-03-04 the registrar can deactivate and reactivate a credential type', function () {
    $type = CredentialType::factory()->create();

    $component = Livewire::actingAs($this->registrar)->test(Index::class)->call('toggleStatus', $type->id);
    expect($type->fresh()->is_active)->toBeFalse();

    $component->call('toggleStatus', $type->id);
    expect($type->fresh()->is_active)->toBeTrue()
        ->and(method_exists(Index::class, 'delete'))->toBeFalse();
});

test('REQ-03-05 credential types without steps are flagged and not requestable', function () {
    $empty = CredentialType::factory()->create(['name' => 'Empty One']);
    $ready = CredentialType::factory()->withSteps(['college'])->create();
    CredentialType::factory()->inactive()->withSteps(['college'])->create();

    $this->actingAs($this->registrar)->get(route('registrar.credential-types'))->assertSee('No workflow');

    expect(CredentialType::requestable()->pluck('id')->all())->toBe([$ready->id]);
});

test('REQ-03-11 non-registrars cannot manage credential types', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $type = CredentialType::factory()->create();

    $this->actingAs($user)->get(route('registrar.credential-types'))->assertForbidden();
    $this->actingAs($user)->get(route('registrar.credential-types.workflow', $type))->assertForbidden();
    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff']);
