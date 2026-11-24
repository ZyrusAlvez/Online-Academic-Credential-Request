<?php

namespace App\Console\Commands;

use App\Support\Sms\SmsRouter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sends one text right away through the provider the number would use, to check the SMS settings
 * in .env (REQ-36-10). Not redirected: the number given is the one texted.
 */
class TestSms extends Command
{
    protected $signature = 'app:test-sms {number : E.164 number to text, e.g. +639171234567}';

    protected $description = 'Send a test text message using the current environment\'s SMS settings';

    public function handle(SmsRouter $router): int
    {
        $number = (string) $this->argument('number');

        if (preg_match('/^\+[1-9][0-9]{6,14}$/', $number) !== 1) {
            $this->error('Give the number in E.164 form, e.g. +639171234567.');

            return self::FAILURE;
        }

        $sender = $router->for($number);
        $this->line("Environment: {$this->laravel->environment()} | provider: {$sender->name()}");

        try {
            $sender->send($number, 'UPHSL Credential Tracker ('.$this->laravel->environment().'): test text. If you can read it, SMS is working.');
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(str_starts_with($sender->name(), 'log')
            ? 'Written to storage/logs/laravel.log (this provider\'s keys are not set).'
            : 'Sent. Check the phone.');

        return self::SUCCESS;
    }
}
