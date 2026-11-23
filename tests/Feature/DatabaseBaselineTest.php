<?php

use App\Console\Commands\ResetApp;
use App\Models\Department;
use App\Services\RequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
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

test('REQ-31-01 app:reset-demo refuses to run in production', function () {
    fakeUploads();
    $migrate = fakeMigrateFresh();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:reset-demo')->assertFailed();

    expect($migrate->calls)->toBe([])
        ->and(Storage::disk(RequestWorkflow::proofDisk())->allFiles())->toHaveCount(4);
});
