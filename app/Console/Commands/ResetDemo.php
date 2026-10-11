<?php

namespace App\Console\Commands;

use App\Services\RequestWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds the database with the base and demo data. Runs automatically after the test
 * suite, because tests share the application database.
 */
class ResetDemo extends Command
{
    protected $signature = 'app:reset-demo';

    protected $description = 'Wipe the database and reload the demo accounts and requests';

    public function handle(): int
    {
        if (! $this->laravel->environment('staging')) {
            $this->error('Demo data can only be reset in the staging environment.');

            return self::FAILURE;
        }

        Storage::disk(RequestWorkflow::PROOF_DISK)->deleteDirectory('payment-proofs');

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Demo data reloaded.');

        return self::SUCCESS;
    }
}
