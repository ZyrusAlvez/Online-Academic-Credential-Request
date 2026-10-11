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
 * Tests share the application database and wipe it, so after every run (pass or fail)
 * reload the demo data with `php artisan app:reset-demo`. Skipped on CI.
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
                fwrite(STDOUT, PHP_EOL.'  Reloading demo data (tests share the app database)… ');

                $process = new Process([PHP_BINARY, 'artisan', 'app:reset-demo'], dirname(__DIR__, 2), ['APP_ENV' => 'local'], timeout: 300);
                $process->run();

                fwrite(STDOUT, ($process->isSuccessful() ? 'done.' : 'FAILED – run `php artisan app:reset-demo`.').PHP_EOL);
            }
        });
    }
}
