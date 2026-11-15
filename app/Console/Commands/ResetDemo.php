<?php

namespace App\Console\Commands;

use Database\Seeders\DemoRequestSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * The same reset as `app:reset`, followed by the demo accounts, and the sample requests only with
 * `--requests`. Local and staging only (REQ-23-07, REQ-23-08).
 */
class ResetDemo extends Command
{
    protected $signature = 'app:reset-demo {--requests : Also load the sample requests}';

    protected $description = 'Wipe the database and reload the base data and the demo accounts (no requests unless --requests)';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'staging'])) {
            $this->error('Demo data can only be reset in the local and staging environments.');

            return self::FAILURE;
        }

        if ($this->call('app:reset') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $withRequests = (bool) $this->option('requests');

        $this->call('db:seed', ['--class' => $withRequests ? DemoRequestSeeder::class : DemoSeeder::class, '--force' => true]);

        $this->info($withRequests ? 'Demo data reloaded.' : 'Demo accounts reloaded.');

        return self::SUCCESS;
    }
}
