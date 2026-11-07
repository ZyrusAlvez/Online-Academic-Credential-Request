<?php

use App\Actions\Audit\RecordRequestEvent;
use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\ActivityCategory;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\Role;
use App\Livewire\Admin\CredentialTypes\Index as CredentialTypes;
use App\Livewire\Admin\Logs\Index;
use App\Livewire\Admin\Users\Index as Accounts;
use App\Models\ActivityLog;
use App\Models\CredentialType;
use App\Models\Purpose;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Rita', 'middle_name' => null, 'last_name' => 'Registrar']);
    // Setup, accounts and the Logs page are the Admin's (REQ-18-04).
    $this->admin = User::factory()->admin()->create(['first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Admin']);
});

test('REQ-14-01 request actions are logged with the actor, the reference no. and the office', function () {
    $owner = requester($this->org->ccs);
    $request = acceptRequest(submitRequest($owner, $this->org->tor), $this->registrar);

    $submitted = ActivityLog::where('action', 'request.submitted')->sole();
    expect($submitted->category)->toBe(ActivityCategory::Request)
        ->and($submitted->actor_id)->toBe($owner->id)
        ->and($submitted->actor_role)->toBe(Role::Student)
        ->and($submitted->reference_no)->toBe($request->reference_no);

    $verified = ActivityLog::where('action', 'request.payment_verified')->sole();
    expect($verified->actor_id)->toBe($this->registrar->id)
        ->and($verified->actor_name)->toBe('Rita Registrar')
        ->and($verified->reference_no)->toBe($request->reference_no);

    // Every ledger entry has its log entry.
    expect(ActivityLog::where('category', ActivityCategory::Request)->count())->toBe($request->events()->count());

    $forwarded = ActivityLog::where('action', 'request.document_forwarded')->sole();
    expect($forwarded->department_id)->toBe($this->org->registrarOffice->id)
        ->and($forwarded->description)->toContain($this->org->registrarOffice->name);
});

test('REQ-14-02 setup changes are logged with each changed field', function () {
    $this->actingAs($this->admin);

    $this->org->tor->update(['fee' => '600.00']);
    $purpose = Purpose::create(['name' => 'Migration abroad']);

    $edit = ActivityLog::where('action', 'credential_type.updated')->sole();
    expect($edit->category)->toBe(ActivityCategory::Setup)
        ->and($edit->description)->toBe('Updated credential type “Transcript of Records”')
        ->and($edit->changes)->toBe(['fee' => ['530.00', '600.00']])
        ->and($edit->actor_id)->toBe($this->admin->id);

    $added = ActivityLog::where('action', 'purpose.created')->sole();
    expect($added->description)->toBe('Added purpose “Migration abroad”')
        ->and($added->changes['name'])->toBe([null, 'Migration abroad'])
        ->and($added->subject_id)->toBe($purpose->id);

    // Deactivating from the Credential Types page names what happened.
    Livewire::test(CredentialTypes::class)->call('toggleStatus', $this->org->free->id);

    expect(ActivityLog::where('action', 'credential_type.updated')->latest('id')->first()->description)
        ->toBe('Deactivated credential type “Certification of Enrollment”');
});

test('REQ-14-02 seeding and migrating write nothing to the log', function () {
    CredentialType::factory()->create();
    Purpose::create(['name' => 'Seeded purpose']);

    expect(ActivityLog::count())->toBe(0);
});

test('REQ-14-03 account changes are logged, including activation and the requester\'s own profile', function () {
    Livewire::actingAs($this->admin)->test(Accounts::class)
        ->call('create')
        ->set('form.first_name', 'Lina')->set('form.last_name', 'Librarian')
        ->set('form.email', 'lina@uphsl.edu.ph')->set('form.role', 'department')
        ->set('form.department_id', $this->org->vpf->id)
        ->set('form.password', 'Secret123!')
        ->call('save')
        ->assertHasNoErrors();

    $staff = User::where('email', 'lina@uphsl.edu.ph')->sole();
    $created = ActivityLog::where('action', 'user.created')->sole();
    expect($created->category)->toBe(ActivityCategory::Account)
        ->and($created->description)->toBe('Created the Department Staff account of Lina Librarian (lina@uphsl.edu.ph)')
        ->and($created->changes['department_id'])->toBe([null, 'Office of the VP for Finance']);

    Livewire::actingAs($this->admin)->test(Accounts::class)->call('toggleStatus', $staff->id);
    expect(ActivityLog::where('action', 'user.updated')->latest('id')->first()->description)
        ->toBe('Deactivated the account of Lina Librarian (lina@uphsl.edu.ph)');

    $owner = requester($this->org->ccs);
    $this->actingAs($owner);
    $owner->update(['contact_number' => '09171234567']);

    $own = ActivityLog::where('actor_id', $owner->id)->sole();
    expect($own->description)->toBe('Updated their profile')
        ->and($own->changes)->toHaveKey('contact_number');
});

test('REQ-14-04 uploading, verifying and rejecting a valid ID are logged', function () {
    Storage::fake(config('uphsl.receipts_disk'));
    $owner = User::factory()->withProfile($this->org->ccs)->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz']);
    $owner->identityDocuments()->delete();

    $this->actingAs($owner);
    app(SubmitIdentityDocument::class)->handle($owner, 'Passport', UploadedFile::fake()->image('id.jpg'));
    $request = submitRequest($owner, $this->org->free);

    // The review is the evaluation's decision on the request (specs 15, 16), logged through its
    // ledger (REQ-14-01).
    $this->actingAs($this->registrar);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);
    workflow()->returnToRequester($request, $this->registrar, 'Blurry photo');

    $upload = ActivityLog::where('category', ActivityCategory::Identity)->sole();
    expect($upload->description)->toBe('Uploaded a valid ID (Passport)')
        ->and($upload->actor_id)->toBe($owner->id);

    $review = ActivityLog::where('category', ActivityCategory::Request)->latest('id')->firstOrFail();
    expect($review->description)->toBe("Compliance requested: Rejected: User Identity Verification\nBlurry photo")
        ->and($review->reference_no)->toBe($request->reference_no)
        ->and($review->actor_id)->toBe($this->registrar->id);
});

test('REQ-14-05 sign-ins, sign-outs and failed sign-ins are logged', function () {
    $user = User::factory()->create(['email' => 'juan@example.com']);

    Volt::test('auth.login')->set('email', 'juan@example.com')->set('password', 'wrong')->call('login');
    Volt::test('auth.login')->set('email', 'juan@example.com')->set('password', 'password')->call('login');
    $this->post('/logout');

    $logs = ActivityLog::where('category', ActivityCategory::SignIn)->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['sign_in.failed', 'sign_in.login', 'sign_in.logout'])
        ->and($logs[0]->actor_id)->toBeNull()
        ->and($logs[0]->description)->toBe('Failed sign-in for juan@example.com')
        ->and($logs[1]->actor_id)->toBe($user->id)
        ->and($logs[2]->actor_id)->toBe($user->id);
});

test('REQ-14-06 an entry keeps the time, the actor\'s name and role as they were, and the IP address', function () {
    Carbon::setTestNow('2026-11-06 09:15:00');
    $this->actingAs($this->registrar);
    $this->org->tor->update(['fee' => '600.00']);

    $this->registrar->update(['last_name' => 'Santos']);

    $log = ActivityLog::where('action', 'credential_type.updated')->sole();
    expect($log->created_at->format('Y-m-d H:i'))->toBe('2026-11-06 09:15')
        ->and($log->actor_name)->toBe('Rita Registrar')
        ->and($log->actor_role)->toBe(Role::Registrar)
        ->and($log->ip_address)->toBe('127.0.0.1');
});

test('REQ-14-07 log entries cannot be edited or deleted', function () {
    $this->actingAs($this->registrar);
    $this->org->tor->update(['fee' => '600.00']);
    $log = ActivityLog::sole();

    expect(fn () => $log->update(['description' => 'Nothing happened']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

test('REQ-14-08 passwords and tokens are never logged, but a password change is', function () {
    $this->actingAs($this->registrar);

    Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'New-password1')
        ->set('password_confirmation', 'New-password1')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $this->registrar->forceFill(['remember_token' => 'abc', 'google_id' => '12345'])->save();

    $logs = ActivityLog::where('category', ActivityCategory::Account)->get();
    expect($logs)->toHaveCount(1)
        ->and($logs[0]->description)->toBe('Changed their password')
        ->and($logs[0]->changes)->toBeNull();

    $stored = ActivityLog::query()->toBase()->get()->toJson();
    expect($stored)->not->toContain('New-password1')
        ->not->toContain($this->registrar->fresh()->password)
        ->not->toContain('12345');
});

test('REQ-14-09 the admin sees the log newest first with who, what, the transaction ID, the office and the changes', function () {
    $owner = requester($this->org->ccs);
    $request = acceptRequest(submitRequest($owner, $this->org->tor), $this->registrar);

    $this->actingAs($this->admin);
    $this->org->tor->update(['fee' => '600.00']);

    $this->get(route('admin.logs'))
        ->assertOk()
        ->assertSee('Logs')
        ->assertSeeInOrder(['Updated credential type “Transcript of Records”', 'Fee', '530.00', '600.00', 'Payment verified', 'Submitted'])
        ->assertSee('Rita Registrar')
        ->assertSee(Role::Student->label())
        ->assertSee($request->reference_no)
        ->assertSee($this->org->registrarOffice->name);

    $this->get(route('admin.dashboard'))->assertSee(route('admin.logs'));
});

test('REQ-14-10 the registrar can search by reference no., name, email or action', function () {
    $juan = User::factory()->withProfile($this->org->ccs)->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz', 'email' => 'juan@example.com']);
    $maria = User::factory()->withProfile($this->org->cba)->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Reyes', 'email' => 'maria@example.com']);
    $juansRequest = submitRequest($juan, $this->org->tor);
    $mariasRequest = submitRequest($maria, $this->org->free);
    workflow()->startEvaluation($juansRequest, $this->registrar);

    $page = Livewire::actingAs($this->admin)->test(Index::class);

    $page->set('search', $juansRequest->reference_no)
        ->assertSee('Evaluation started')->assertDontSee($mariasRequest->reference_no);

    // A requester's name also finds what staff did on their requests.
    $page->set('search', 'Juan Cruz')
        ->assertSee('Evaluation started')->assertSee($juansRequest->reference_no)->assertDontSee($mariasRequest->reference_no);

    $page->set('search', 'maria@example.com')
        ->assertSee($mariasRequest->reference_no)->assertDontSee($juansRequest->reference_no);

    $page->set('search', 'Evaluation started')
        ->assertSee($juansRequest->reference_no)->assertDontSee($mariasRequest->reference_no);
});

test('REQ-14-11 the registrar can filter by role, kind of action, office and date', function () {
    $owner = requester($this->org->ccs);
    Carbon::setTestNow('2026-11-02 10:00:00');
    $request = acceptRequest(submitRequest($owner, $this->org->tor), $this->registrar);

    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->actingAs($this->registrar);
    $this->org->tor->update(['fee' => '600.00']);
    Volt::test('auth.login')->set('email', 'nobody@example.com')->set('password', 'wrong')->call('login');

    $page = Livewire::actingAs($this->admin)->test(Index::class);

    $page->set('role', 'student')
        ->assertSee('Submitted')->assertDontSee('Payment verified')->assertDontSee('Updated credential type');
    $page->set('role', 'system')
        ->assertSee('Failed sign-in for nobody@example.com')->assertDontSee('Submitted');

    $page->set('role', '')->set('category', 'setup')
        ->assertSee('Updated credential type')->assertDontSee($request->reference_no);

    $page->set('category', '')->set('office', (string) $this->org->registrarOffice->id)
        ->assertSee('Document forwarded')->assertDontSee('Updated credential type')->assertDontSee('Payment verified');

    $page->set('office', '')->set('from', '2026-11-05')->set('to', '2026-11-05')
        ->assertSee('Updated credential type')->assertDontSee($request->reference_no);

    $page->set('from', '2026-11-01')->set('to', '2026-11-02')
        ->assertSee($request->reference_no)->assertDontSee('Updated credential type');
});

test('REQ-14-12 a request entry links to the request\'s record', function () {
    $request = submitRequest(requester($this->org->ccs), $this->org->tor);

    $this->actingAs($this->admin)->get(route('admin.logs'))
        ->assertSee(route('records.show', $request), false);   // the Admin's read-only record (REQ-18-06)
});

test('REQ-14-14 copying an existing ledger entry keeps its time and leaves no IP address', function () {
    Carbon::setTestNow('2026-10-20 08:30:00');
    $request = submitRequest(requester($this->org->ccs), $this->org->tor);
    $event = $request->events()->with(['actor', 'department'])->sole();

    // What the migration does for every ledger entry that predates the log.
    DB::table('activity_logs')->delete();
    Carbon::setTestNow('2026-11-06 12:00:00');
    app(RecordRequestEvent::class)->handle($event, $request, $event->actor, at: $event->created_at);

    $log = ActivityLog::sole();
    expect($log->action)->toBe('request.submitted')
        ->and($log->reference_no)->toBe($request->reference_no)
        ->and($log->created_at->format('Y-m-d H:i'))->toBe('2026-10-20 08:30')
        ->and($log->ip_address)->toBeNull();
});

test('REQ-14-13 only the admin can open the Logs page', function (string $role) {
    $user = match ($role) {
        'student' => requester($this->org->ccs),
        'department' => User::factory()->departmentStaff($this->org->vpf)->create(),
        'registrar' => $this->registrar,
    };

    $this->actingAs($user)->get(route('admin.logs'))->assertForbidden();
})->with(['student', 'department', 'registrar']);
