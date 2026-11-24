<?php

use App\Actions\Staff\ChangeAccountRole;
use App\Enums\Role;
use App\Livewire\Admin\Departments\Index as Departments;
use App\Livewire\Admin\Users\Index as Accounts;
use App\Livewire\Registrar\Queue;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->admin = User::factory()->admin()->create(['first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Admin']);
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

/** The Admin's pages (REQ-18-04). */
const ADMIN_PAGES = [
    'admin.dashboard', 'admin.reports', 'admin.credential-types', 'admin.departments', 'admin.programs',
    'admin.purposes', 'admin.accounts', 'admin.calendar', 'admin.logs',
];

/** The Registrar's stage pages (REQ-18-05). */
const STAGE_PAGES = ['registrar.evaluation', 'registrar.payments', 'registrar.outgoing', 'registrar.final-check', 'registrar.release'];

test('REQ-18-04 only the admin opens the dashboard, reports and administration pages', function (string $state) {
    $user = User::factory()->{$state}()->create();

    foreach (ADMIN_PAGES as $page) {
        $this->actingAs($this->admin)->get(route($page))->assertOk();
        $this->actingAs($user)->get(route($page))->assertForbidden();
    }

    // The actions refuse too.
    Livewire::actingAs($user)->test(Departments::class)->assertForbidden();
    Livewire::actingAs($user)->test(Accounts::class)->assertForbidden();
})->with(['registrar', 'departmentStaff', 'student']);

test('REQ-18-05 the admin cannot open the stage pages or act on a request', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    foreach (STAGE_PAGES as $page) {
        $this->actingAs($this->admin)->get(route($page))->assertForbidden();
    }

    $this->actingAs($this->admin)->get(route('registrar.requests.show', $request))->assertForbidden();
    expect($this->admin->can('evaluate', $request))->toBeFalse()
        ->and($this->admin->can('review', $request))->toBeFalse();
});

test('REQ-18-06 Records and a request\'s record are for the registrar and the admin only', function () {
    $request = submitRequest($this->owner, $this->org->tor);

    foreach ([$this->registrar, $this->admin] as $user) {
        $this->actingAs($user)->get(route('records'))->assertOk()->assertSee($request->reference_no);
        $this->actingAs($user)->get(route('records.show', $request))->assertOk()->assertSee('Tracking History');
    }

    $this->actingAs($this->owner)->get(route('records'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff($this->org->ccs)->create())->get(route('records.show', $request))->assertForbidden();
});

test('REQ-18-07 the admin opens a request\'s valid ID and proof of payment', function () {
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($request);
    $request = $request->fresh();
    Storage::disk(RequestWorkflow::proofDisk())->put($request->identityDocument->path, 'id');

    $this->actingAs($this->admin)->get(route('identity-documents.show', $request->identityDocument))->assertOk();
    $this->actingAs($this->admin)->get(route('payment-proofs.show', $request->paymentProofs()->firstOrFail()))->assertOk();

    // Department staff still can't.
    $this->actingAs(User::factory()->departmentStaff($this->org->ccs)->create())
        ->get(route('identity-documents.show', $request->identityDocument))->assertNotFound();
});

test('REQ-18-10 an admin cannot change their own role or deactivate their own account', function () {
    Livewire::actingAs($this->admin)->test(Accounts::class)
        ->call('toggleStatus', $this->admin->id)->assertForbidden();
    Livewire::actingAs($this->admin)->test(Accounts::class)
        ->call('editRole', $this->admin->id)->assertForbidden();

    expect(fn () => app(ChangeAccountRole::class)->handle($this->admin, $this->admin, Role::Registrar))->toThrow(DomainException::class)
        ->and($this->admin->fresh())->role->toBe(Role::Admin)->is_active->toBeTrue();

    // Another admin can be deactivated, so one always remains: the one acting.
    $other = User::factory()->admin()->create();
    Livewire::actingAs($this->admin)->test(Accounts::class)->call('toggleStatus', $other->id);
    expect($other->fresh()->is_active)->toBeFalse();
});

test('REQ-18-11 without the settings the seeder refuses to seed', function (string $missing, string $setting) {
    config(["uphsl.initial_admin.{$missing}" => null]);
    $before = User::count();

    expect(fn () => $this->seed(AdminSeeder::class))->toThrow(RuntimeException::class, $setting)
        ->and(User::count())->toBe($before);
})->with([['email', 'INITIAL_ADMIN_EMAIL'], ['password', 'INITIAL_ADMIN_PASSWORD']]);

test('REQ-18-05 the admin cannot forward from Outgoing', function () {
    Livewire::actingAs($this->admin)->test(Queue::class, ['queue' => 'outgoing'])
        ->call('forward', acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar)->id)
        ->assertForbidden();
});
