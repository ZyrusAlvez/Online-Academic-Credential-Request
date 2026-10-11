<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use App\Services\TurnaroundPredictor;
use Illuminate\Database\Eloquent\Model;
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
        // Only two environments exist. The test runner's internal `testing` mode is allowed
        // because Laravel's test helpers depend on it; it always uses the staging database.
        if (! $this->app->environment(['staging', 'production']) && ! $this->app->runningUnitTests()) {
            throw new RuntimeException(sprintf(
                'APP_ENV must be "staging" or "production", "%s" given. Check your .env file.',
                $this->app->environment(),
            ));
        }

        // Catch N+1 queries and silently discarded attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        // Re-apply role checks on Livewire's follow-up (/livewire/update) requests.
        Livewire::addPersistentMiddleware([EnsureUserHasRole::class]);
    }
}
