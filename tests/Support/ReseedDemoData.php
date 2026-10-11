<?php

namespace Tests\Support;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Process\Process;

/**
 * Tests wipe the staging database, so after every run (pass or fail) reload its demo data
 * with `php artisan app:reset-demo`. Skipped on CI.
 */
final class ReseedDemoData implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (getenv('CI') !== false) {
            return;
        }

        $facade->registerSubscriber(new class implements FinishedSubscriber
        {
            public function notify(Finished $event): void
            {
                fwrite(STDOUT, PHP_EOL.'  Reloading staging demo data… ');

                // Drop phpunit.xml's test overrides (weak bcrypt rounds, array mail/cache, sync
                // queue) so the demo data is seeded exactly as `php artisan app:reset-demo` would.
                $env = array_fill_keys(['BCRYPT_ROUNDS', 'CACHE_STORE', 'MAIL_MAILER', 'QUEUE_CONNECTION', 'SESSION_DRIVER', 'APP_MAINTENANCE_DRIVER', 'PULSE_ENABLED', 'TELESCOPE_ENABLED'], false);
                $process = new Process([PHP_BINARY, 'artisan', 'app:reset-demo'], dirname(__DIR__, 2), ['APP_ENV' => 'staging', 'DB_DATABASE' => 'uphsl_credential_tracker_staging', ...$env], timeout: 300);
                $process->run();

                fwrite(STDOUT, ($process->isSuccessful() ? 'done.' : 'FAILED – run `php artisan app:reset-demo`.').PHP_EOL);
            }
        });
    }
}
