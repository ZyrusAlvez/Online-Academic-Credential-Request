<?php

use App\Actions\Staff\ChangeAccountRole;
use App\Enums\Role;
use App\Livewire\Admin\Departments\Index as Departments;
use App\Livewire\Admin\Users\Index as Accounts;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as Records;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
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
    'admin.purposes', 'admin.users', 'admin.calendar', 'admin.logs',
];

/** The Registrar's stage pages (REQ-18-05). */
const STAGE_PAGES = ['registrar.evaluation', 'registrar.payments', 'registrar.in-progress', 'registrar.final-check', 'registrar.release'];

// ── Roles and pages ─────────────────────────────────────────────────────────────────────────

test('REQ-18-01 there are four roles: Requester, Registrar, Department Staff and Admin', function () {
    expect(collect(Role::cases())->map->value->all())->toBe(['student', 'registrar', 'department', 'admin'])
        ->and(Role::Admin->label())->toBe('Admin')
        ->and(Role::Department->label())->toBe('Department Staff');
});

test('REQ-18-02 the admin has Overview and Administration in the sidebar and lands on the dashboard', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.dashboard'))->assertOk()->assertSeeInOrder([
        'Overview', 'Dashboard', 'Records', 'Reports',
        'Administration', 'Credential Types', 'Departments', 'Programs', 'Purposes', 'Accounts', 'Working Calendar', 'Logs',
    ])->assertDontSee(route('registrar.evaluation'));
});

test('REQ-18-03 the registrar has the five stages and Records in the sidebar and lands on Initial Evaluation', function () {
    $this->actingAs($this->registrar)->get('/dashboard')->assertRedirect(route('registrar.evaluation'));
    $this->get('/registrar')->assertRedirect('/registrar/evaluation');

    $this->get(route('registrar.evaluation'))->assertOk()
        ->assertSeeInOrder(['Initial Evaluation', 'Payment Validation', 'In Progress', 'Final Quality Check', 'Release', 'Records'])
        ->assertSee(route('records'))
        ->assertDontSee(route('admin.reports'))
        ->assertDontSee(route('admin.credential-types'))
        ->assertDontSee(route('admin.users'));
});

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
        $this->actingAs($user)->get(route('records.show', $request))->assertOk()->assertSee('Ledger');
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

// ── Dashboard ───────────────────────────────────────────────────────────────────────────────

test('REQ-18-08 the dashboard links open Records and it has no Office of the Registrar queue', function () {
    submitRequest($this->owner, $this->org->free);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee(route('records', ['status' => 'needs_registrar']), false)
        ->assertSee(route('records', ['status' => 'waiting_for_requester']), false)
        ->assertSee(route('records', ['status' => 'overdue']), false)
        ->assertSee(route('admin.reports'), false)
        ->assertDontSee('Office of the Registrar queue')
        ->assertDontSee(route('registrar.evaluation'), false);

    // The tile's filter lists what it counted.
    Livewire::actingAs($this->admin)->test(Records::class)
        ->set('status', 'needs_registrar')
        ->assertSee(CredentialRequest::sole()->reference_no);
});

// ── Accounts ────────────────────────────────────────────────────────────────────────────────

test('REQ-18-09 the admin creates admin and registrar accounts without a department, and changes a role to Admin', function (string $role) {
    Livewire::actingAs($this->admin)->test(Accounts::class)
        ->call('create')
        ->set('form.first_name', 'New')->set('form.last_name', 'Staff')
        ->set('form.email', "new.{$role}@uphsl.edu.ph")->set('form.password', 'Secret123!')
        ->set('form.role', $role)
        ->set('form.department_id', $this->org->ccs->id)
        ->call('save')
        ->assertHasNoErrors();

    $account = User::where('email', "new.{$role}@uphsl.edu.ph")->sole();
    expect($account->role)->toBe(Role::from($role))
        ->and($account->department_id)->toBeNull()
        ->and($account->hasVerifiedEmail())->toBeTrue();

    $staff = User::factory()->departmentStaff($this->org->ccs)->create();
    Livewire::actingAs($this->admin)->test(Accounts::class)
        ->call('editRole', $staff->id)
        ->set('roleForm.role', 'admin')
        ->call('changeRole')
        ->assertHasNoErrors();

    expect($staff->fresh())->role->toBe(Role::Admin)->department_id->toBeNull();
})->with(['admin', 'registrar']);

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

// ── The first admin ─────────────────────────────────────────────────────────────────────────

test('REQ-18-11 the seeder creates the first admin once and never changes it', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);   // idempotent

    $admin = User::where('email', config('uphsl.initial_admin.email'))->sole();
    expect($admin)->role->toBe(Role::Admin)->is_active->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue();

    config(['uphsl.initial_admin.password' => 'a-different-password']);
    $this->seed(AdminSeeder::class);
    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();

    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
});

test('REQ-18-11 without the settings the seeder skips the admin and seeding goes on', function (string $missing) {
    config(["uphsl.initial_admin.{$missing}" => null]);
    $before = User::count();

    $this->seed(AdminSeeder::class);

    expect(User::count())->toBe($before)
        ->and(User::where('role', Role::Admin)->whereKeyNot($this->admin->id)->exists())->toBeFalse();
})->with(['email', 'password']);

test('REQ-18-12 the demo data includes the admin@example.com admin', function () {
    DemoSeeder::adminAccount();
    DemoSeeder::adminAccount();   // the demo reset runs it again

    $demo = User::where('email', 'admin@example.com')->sole();
    expect($demo)->role->toBe(Role::Admin)->is_active->toBeTrue()
        ->and(Hash::check('@Test123', $demo->password))->toBeTrue();
});

test('REQ-07-09 every demo account uses @Test123, which meets the password rules', function () {
    expect(DemoSeeder::PASSWORD)->toBe('@Test123')
        ->and(Validator::make(['password' => DemoSeeder::PASSWORD], ['password' => Password::defaults()])->passes())->toBeTrue();

    $office = DemoSeeder::staffAccount($this->org->vpf);
    expect(Hash::check(DemoSeeder::PASSWORD, $office->password))->toBeTrue();
});

test('REQ-18-05 the admin cannot forward from In Progress', function () {
    Livewire::actingAs($this->admin)->test(Queue::class, ['queue' => 'in_progress'])
        ->call('forward', acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar)->id)
        ->assertForbidden();
});
