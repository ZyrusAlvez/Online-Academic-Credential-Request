<?php

namespace App\Console\Commands;

use Carbon\Exceptions\InvalidFormatException;
use Database\Seeders\DemoRequestSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The same reset as `app:reset`, followed by the demo accounts, the sample requests and months of
 * generated activity. Local and staging only (REQ-31-01). `--as-of` dates the data as if it were
 * loaded at that moment (REQ-31-06).
 */
class ResetDemo extends Command
{
    protected $signature = 'app:reset-demo
        {--as-of= : Date the demo data as of this moment, e.g. "2026-11-20 08:00" (default: now)}';

    protected $description = 'Wipe the database and reload the base data, the demo accounts and the demo requests';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'staging'])) {
            $this->error('Demo data can only be reset in the local and staging environments.');

            return self::FAILURE;
        }

        // Read before anything is wiped (REQ-31-06).
        $asOf = $this->option('as-of') ?? config('uphsl.demo.as_of');

        if (filled($asOf)) {
            try {
                $moment = Carbon::parse((string) $asOf, (string) config('app.timezone'));
            } catch (InvalidFormatException) {
                $this->error("Cannot read the as-of moment \"{$asOf}\". Use a date and time such as \"2026-11-20 08:00\".");

                return self::FAILURE;
            }

            config(['uphsl.demo.as_of' => $moment->toDateTimeString()]);
            $this->info("The demo data is dated as of {$moment->format('l, F j, Y g:i A')}.");
        }

        if ($this->call('app:reset') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Loading the demo data: this takes a few minutes.');
        $this->call('db:seed', ['--class' => DemoRequestSeeder::class, '--force' => true]);

        $this->info('Demo data reloaded.');

        return self::SUCCESS;
    }
}
