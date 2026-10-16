<?php

namespace App\Console\Commands;

use App\Services\RequestWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds the database with the base and demo data. Local and staging only.
 */
class ResetDemo extends Command
{
    protected $signature = 'app:reset-demo';

    protected $description = 'Wipe the database and reload the demo accounts and requests';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'staging'])) {
            $this->error('Demo data can only be reset in the local and staging environments.');

            return self::FAILURE;
        }

        // The registrar is recreated after the wipe, so check its settings before deleting anything.
        if (blank(config('uphsl.initial_registrar.email')) || blank(config('uphsl.initial_registrar.password'))) {
            $this->error('Set INITIAL_REGISTRAR_EMAIL and INITIAL_REGISTRAR_PASSWORD in .env first. Nothing was changed.');

            return self::FAILURE;
        }

        Storage::disk(RequestWorkflow::proofDisk())->deleteDirectory('payment-proofs');

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Demo data reloaded.');

        return self::SUCCESS;
    }
}
