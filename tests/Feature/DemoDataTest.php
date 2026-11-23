<?php

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\DemoRequestSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Spec 31: the demo data app:reset-demo loads, generated through the real workflow. Kept small here
| (phpunit.xml); the servers use the defaults.
*/

beforeEach(fn () => Storage::fake(RequestWorkflow::proofDisk()));

/** Accounts made for the data, not the ones listed in credentials.md. */
function generatedRequesters(): Collection
{
    $listed = array_column(DemoSeeder::REQUESTERS, 0);

    return User::where('role', Role::Student)->whereNotIn('email', $listed)->where('email', 'not like', 'walkin.%')->get();
}

test('REQ-31-06 an as-of moment that cannot be read stops app:reset-demo before anything is wiped', function () {
    app()['env'] = 'local';
    $kept = User::factory()->create();

    $this->artisan('app:reset-demo', ['--as-of' => 'next presentation'])
        ->expectsOutputToContain('Cannot read the as-of moment')
        ->assertFailed();

    expect(User::find($kept->id))->not->toBeNull();
});

test('REQ-31-05 the generated history notifies no one and is never dated after the moment it is loaded', function () {
    config(['uphsl.demo.applications' => 20]);
    $before = now();
    $this->seed(DemoRequestSeeder::class);

    $generated = generatedRequesters()->pluck('id');

    expect(DB::table('notifications')->whereIn('notifiable_id', $generated)->count())->toBe(0)
        ->and(RequestEvent::where('created_at', '>', now())->count())->toBe(0)
        ->and(CredentialRequest::max('submitted_at'))->not->toBeGreaterThan(now()->toDateTimeString())
        ->and(now()->greaterThanOrEqualTo($before))->toBeTrue();
});
