<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\ActivityCategory;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\Role;
use App\Livewire\Admin\CredentialTypes\Index as CredentialTypes;
use App\Livewire\Admin\Logs\Index;
use App\Livewire\Admin\Users\Index as Accounts;
use App\Models\ActivityLog;
use App\Models\Purpose;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
    workflow()->returnForClarification($request, $this->registrar, 'Blurry photo');

    $upload = ActivityLog::where('category', ActivityCategory::Identity)->sole();
    expect($upload->description)->toBe('Uploaded a valid ID (Passport)')
        ->and($upload->actor_id)->toBe($owner->id);

    $review = ActivityLog::where('category', ActivityCategory::Request)->latest('id')->firstOrFail();
    expect($review->description)->toBe("Customer action requested: Rejected: User Identity Verification\nBlurry photo")
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
        ->and($logs[0]->actor_id)->toBe($user->id)
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

test('REQ-14-15 a failed sign-in is logged under the account whose email was typed, or as the typed email with no account', function () {
    $owner = requester($this->org->ccs);

    Volt::test('auth.login')->set('email', $owner->email)->set('password', 'wrong')->call('login');
    Volt::test('auth.login')->set('email', 'nobody@example.com')->set('password', 'wrong')->call('login');

    [$known, $unknown] = ActivityLog::where('action', 'sign_in.failed')->orderBy('id')->get();
    expect($known->actor_id)->toBe($owner->id)
        ->and($known->actor_name)->toBe($owner->name)
        ->and($known->actor_role)->toBe(Role::Student)
        ->and($unknown->actor_id)->toBeNull()
        ->and($unknown->actor_name)->toBe('nobody@example.com')
        ->and($unknown->actor_role)->toBeNull();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSeeInOrder(['nobody@example.com', 'No account', 'Failed sign-in for nobody@example.com'])
        ->assertSeeInOrder([$owner->name, Role::Student->label(), "Failed sign-in for {$owner->email}"]);
});

test('REQ-14-13 only the admin can open the Logs page', function (string $role) {
    $user = match ($role) {
        'student' => requester($this->org->ccs),
        'department' => User::factory()->departmentStaff($this->org->vpf)->create(),
        'registrar' => $this->registrar,
    };

    $this->actingAs($user)->get(route('admin.logs'))->assertForbidden();
})->with(['student', 'department', 'registrar']);
