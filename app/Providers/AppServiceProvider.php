<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 queries and silently discarded attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        // Re-apply role checks on Livewire's follow-up (/livewire/update) requests.
        Livewire::addPersistentMiddleware([EnsureUserHasRole::class]);
    }
}
