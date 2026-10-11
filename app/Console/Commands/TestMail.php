<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one email right away (not queued) to check the mail settings in .env.
 */
class TestMail extends Command
{
    protected $signature = 'app:test-mail {email : Address to send the test email to}';

    protected $description = 'Send a test email using the current environment\'s mail settings';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $this->line("Environment: {$this->laravel->environment()} | mailer: {$mailer}");

        try {
            Mail::raw(
                'This is a test email from the UPHSL Credential Tracker ('.$this->laravel->environment().'). If you can read it, email is working.',
                fn ($message) => $message->to((string) $this->argument('email'))->subject('UPHSL Credential Tracker: test email'),
            );
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Written to storage/logs/laravel.log (this environment does not send real email).'
            : 'Sent. Check the inbox (and the spam folder).');

        return self::SUCCESS;
    }
}
