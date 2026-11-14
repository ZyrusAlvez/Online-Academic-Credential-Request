<?php

use App\Console\Commands\ResetApp;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces `migrate:fresh` with a stand-in that records how it was called, so a reset can be tested
 * without dropping the tables under the test run.
 */
function fakeMigrateFresh(): Command
{
    $fake = new class extends Command
    {
        protected $signature = 'migrate:fresh {--seed} {--force}';

        /** @var list<array{seed: bool, force: bool}> */
        public array $calls = [];

        public function handle(): int
        {
            $this->calls[] = ['seed' => (bool) $this->option('seed'), 'force' => (bool) $this->option('force')];

            return self::SUCCESS;
        }
    };

    Artisan::registerCommand($fake);

    return $fake;
}

/**
 * Puts one file in each uploads folder, and one elsewhere on the same disk.
 */
function fakeUploads(): void
{
    $disk = Storage::fake(RequestWorkflow::proofDisk());

    foreach (['payment-proofs/a.png', 'identity-documents/b.png', 'request-documents/c.pdf', 'other/keep.txt'] as $path) {
        $disk->put($path, 'x');
    }
}

test('REQ-23-01 the migrations alone build every table, and add no rows except the request statuses', function () {
    $expected = [
        'activity_logs', 'cache', 'cache_locks', 'credential_requests', 'credential_types', 'delay_notes', 'departments',
        'due_date_pauses', 'email_verification_codes', 'failed_jobs', 'identity_documents', 'job_batches', 'jobs',
        'non_working_days', 'notifications', 'office_visits', 'password_reset_tokens', 'payment_proofs', 'programs',
        'purposes', 'releases', 'reminder_logs', 'request_checks', 'request_deficiencies', 'request_documents',
        'request_events', 'request_representatives', 'request_statuses', 'request_steps', 'requester_verifications',
        'sessions', 'users', 'workflow_steps',
    ];

    $tables = collect(Schema::getTables(DB::connection()->getDatabaseName()))->pluck('name')->reject(fn (string $name) => $name === 'migrations')->sort()->values()->all();

    expect($tables)->toBe($expected)
        ->and(array_values(array_filter($expected, fn (string $table) => DB::table($table)->exists())))->toBe(['request_statuses']);
});

test('REQ-23-04 app:reset deletes the uploaded files and rebuilds the database with the base data only', function () {
    fakeUploads();
    $migrate = fakeMigrateFresh();

    $this->artisan('app:reset')->expectsOutputToContain('Reset done')->assertSuccessful();

    $disk = Storage::disk(RequestWorkflow::proofDisk());

    // `--seed` runs DatabaseSeeder, which loads the base data and no demo data (REQ-23-02).
    expect($migrate->calls)->toBe([['seed' => true, 'force' => true]])
        ->and($disk->allFiles())->toBe(['other/keep.txt']);
});

test('REQ-23-05 app:reset refuses to start without the first admin settings, changing nothing', function (string $missing) {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    $department = Department::factory()->create();
    config(["uphsl.initial_admin.{$missing}" => null]);

    $this->artisan('app:reset')->expectsOutputToContain('Nothing was changed')->assertFailed();

    expect($migrate->calls)->toBe([])
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toHaveCount(4)
        ->and($department->fresh())->not->toBeNull();
})->with(['email', 'password']);

test('REQ-23-06 in production app:reset stops unless the confirmation phrase is typed', function (string $answer) {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:reset')
        ->expectsQuestion('Type "'.ResetApp::PRODUCTION_PHRASE.'" to continue', $answer)
        ->expectsOutputToContain('Nothing was changed')
        ->assertFailed();

    expect($migrate->calls)->toBe([])
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toHaveCount(4);
})->with(['', 'yes', 'RESET PRODUCTION', 'reset staging']);

test('REQ-23-06 in production app:reset runs once the confirmation phrase is typed', function () {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:reset')
        ->expectsQuestion('Type "'.ResetApp::PRODUCTION_PHRASE.'" to continue', ResetApp::PRODUCTION_PHRASE)
        ->assertSuccessful();

    expect($migrate->calls)->toBe([['seed' => true, 'force' => true]])
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toBe(['other/keep.txt']);
});

test('REQ-23-06 local and staging are not asked to confirm', function (string $environment) {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => $environment);

    $this->artisan('app:reset')->assertSuccessful();

    expect($migrate->calls)->toHaveCount(1);
})->with(['local', 'staging']);

test('REQ-23-07 app:reset-demo does the same reset and then loads the demo data', function () {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => 'staging');

    $this->artisan('app:reset-demo')->expectsOutputToContain('Demo data reloaded')->assertSuccessful();

    expect($migrate->calls)->toBe([['seed' => true, 'force' => true]])
        ->and(User::where('email', 'registrar@example.com')->sole()->role)->toBe(Role::Registrar)
        ->and(User::where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(User::where('email', config('uphsl.initial_admin.email'))->exists())->toBeTrue();
});

test('REQ-23-07 app:reset-demo refuses to run in production', function () {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:reset-demo')->assertFailed();

    expect($migrate->calls)->toBe([])
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toHaveCount(4);
});
