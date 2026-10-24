<?php

use App\Enums\RouteTask;
use App\Livewire\Registrar\CredentialTypes\Index;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\CredentialTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-03-01 the seeder creates the 17-type master list with fees, periods and routes', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);

    $tor = CredentialType::where('name', 'Transcript of Records (TOR)')->with('steps.department')->sole();
    $authentication = CredentialType::where('name', 'Authentication')->with('steps.department')->sole();
    $transfer = CredentialType::where('name', 'TOR for Transfer Credentials')->sole();

    expect(CredentialType::count())->toBe(17)
        ->and($tor->fee)->toBe('530.00')
        ->and($tor->processing_days)->toBe(7)
        ->and($tor->routeSummary())->toBe('REG → Dean → VPF → CHAN')
        ->and($tor->steps->map->task->all())->toBe([RouteTask::Prepare, RouteTask::Sign, RouteTask::DocumentaryStamp, RouteTask::Sign])
        ->and($tor->requirements)->toContain('PSA-authenticated birth certificate')
        ->and($authentication->fee)->toBe('50.00')
        ->and($authentication->routeSummary())->toBe('REG')
        ->and(CredentialType::where('name', 'Diploma / Certificate for Non-Degree Programs')->with('steps.department')->sole()->routeSummary())->toBe('REG → CHAN → PRES')
        ->and($transfer->requires_receiving_institution)->toBeTrue()
        ->and($tor->requires_receiving_institution)->toBeFalse();
});

test('REQ-03-01 re-seeding does not duplicate types or overwrite edited routes', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);
    $tor = CredentialType::where('name', 'Transcript of Records (TOR)')->sole();
    $tor->steps()->first()->delete();
    $tor->update(['fee' => '600.00']);

    $this->seed(CredentialTypeSeeder::class);

    expect(CredentialType::count())->toBe(17)
        ->and($tor->steps()->count())->toBe(3)
        ->and($tor->fresh()->fee)->toBe('600.00');
});

test('REQ-03-12 installing the master list replaces the placeholder types and routes', function () {
    $library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
    $this->seed(DepartmentSeeder::class);
    $old = CredentialType::factory()->withSteps([$library])->create(['name' => 'Good Moral Certificate']);
    $tor = CredentialType::factory()->withSteps([$library, 'college'])->create(['name' => 'Transcript of Records (TOR)', 'processing_days' => 10]);

    (new CredentialTypeSeeder)->install();
    (new CredentialTypeSeeder)->install();

    expect($old->fresh()->is_active)->toBeFalse()
        ->and($old->steps()->count())->toBe(1)
        ->and(CredentialType::active()->count())->toBe(17)
        ->and($tor->fresh()->processing_days)->toBe(7)
        ->and($tor->fresh()->load('steps.department')->routeSummary())->toBe('REG → Dean → VPF → CHAN');
});

test('REQ-03-02 the registrar sees credential types with fee, processing period and route', function () {
    $registrarOffice = Department::factory()->registrarOffice()->create();
    CredentialType::factory()->paid('250.00')->withSteps([$registrarOffice, 'college'])->create(['name' => 'Transcript of Records', 'processing_days' => 10]);

    $this->actingAs($this->registrar)->get(route('registrar.credential-types'))
        ->assertOk()
        ->assertSee('Transcript of Records')
        ->assertSee('₱250.00')
        ->assertSee('10 working days')
        ->assertSee('REG → Dean');
});

test('REQ-03-03 the registrar can create a paid credential type with requirements and is sent to its route', function () {
    Livewire::actingAs($this->registrar)->test(Index::class)
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
        ->assertRedirect(route('registrar.credential-types.workflow', CredentialType::sole()));

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

    $this->actingAs($this->registrar)->get(route('registrar.credential-types'))->assertSee('No route');

    expect(CredentialType::requestable()->pluck('id')->all())->toBe([$ready->id]);
});

test('REQ-03-11 non-registrars cannot manage credential types', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $type = CredentialType::factory()->create();

    $this->actingAs($user)->get(route('registrar.credential-types'))->assertForbidden();
    $this->actingAs($user)->get(route('registrar.credential-types.workflow', $type))->assertForbidden();
    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
})->with(['student', 'departmentStaff']);
