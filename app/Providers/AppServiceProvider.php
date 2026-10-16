<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use App\Services\TurnaroundPredictor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load the turnaround model file once per process.
        $this->app->singleton(TurnaroundPredictor::class);
    }

    public function boot(): void
    {
        // Only three environments exist. The test runner's internal `testing` mode is allowed
        // because Laravel's test helpers depend on it; it uses its own _testing database.
        if (! $this->app->environment(['local', 'staging', 'production']) && ! $this->app->runningUnitTests()) {
            throw new RuntimeException(sprintf(
                'APP_ENV must be "local", "staging" or "production", "%s" given. Check your .env file.',
                $this->app->environment(),
            ));
        }

        // Catch N+1 queries and silently discarded attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        if (filled($redirectTo = config('mail.redirect_to'))) {
            Mail::alwaysTo($redirectTo);
        }

        // Re-apply role checks on Livewire's follow-up (/livewire/update) requests.
        Livewire::addPersistentMiddleware([EnsureUserHasRole::class]);
    }
}
