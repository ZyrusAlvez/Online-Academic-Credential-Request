<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * The same reset as `app:reset`, followed by the demo accounts and requests. Local and staging
 * only (REQ-23-07).
 */
class ResetDemo extends Command
{
    protected $signature = 'app:reset-demo';

    protected $description = 'Wipe the database and reload the base data, the demo accounts and the demo requests';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'staging'])) {
            $this->error('Demo data can only be reset in the local and staging environments.');

            return self::FAILURE;
        }

        if ($this->call('app:reset') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Demo data reloaded.');

        return self::SUCCESS;
    }
}
