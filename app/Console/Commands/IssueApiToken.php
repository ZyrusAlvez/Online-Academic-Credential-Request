<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issues a Sanctum API token for an active registrar (spec 08). The token is shown once.
 */
class IssueApiToken extends Command
{
    protected $signature = 'app:api-token {email : Email of an active registrar account} {--name=api : Label for the token}';

    protected $description = 'Issue an API token for a registrar account (shown once)';

    public function handle(): int
    {
        $user = User::where('email', strtolower((string) $this->argument('email')))->first();

        if ($user === null || ! $user->is_active || ! $user->hasRole(Role::Registrar)) {
            $this->error('No active registrar account has that email.');

            return self::FAILURE;
        }

        $days = (int) ceil(((int) config('sanctum.expiration')) / (60 * 24));
        $token = $user->createToken((string) $this->option('name'), ['*'], now()->addMinutes((int) config('sanctum.expiration')));

        $this->line("Environment: {$this->laravel->environment()} | expires in {$days} days");
        $this->line('Token (copy it now, it is not shown again):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
