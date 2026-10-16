<?php

use App\Models\Department;
use Illuminate\Support\Facades\Process;

/**
 * Evaluates a config file as it loads under the given environment variables (null unsets one),
 * then restores the test runner's own values.
 *
 * @param  array<string, string|null>  $vars
 * @return array<string, mixed>
 */
function configUnder(string $file, array $vars): array
{
    $saved = [];

    foreach ($vars as $name => $value) {
        $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
        setEnvironmentVariable($name, $value);
    }

    try {
        /** @var array<string, mixed> */
        return require config_path($file);
    } finally {
        foreach ($saved as $name => [$env, $server, $putenv]) {
            setEnvironmentVariable($name, null);

            if ($env !== null) {
                $_ENV[$name] = $env;
            }
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($putenv !== false) {
                putenv("{$name}={$putenv}");
            }
        }
    }
}

function setEnvironmentVariable(string $name, ?string $value): void
{
    if ($value === null) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);

        return;
    }

    $_ENV[$name] = $_SERVER[$name] = $value;
    putenv("{$name}={$value}");
}

test('the database name follows the environment unless DB_DATABASE is set', function () {
    $database = fn (array $vars) => configUnder('database.php', $vars)['connections']['mysql']['database'];

    expect($database(['APP_ENV' => 'local', 'DB_DATABASE' => null]))->toBe('uphsl_credential_tracker_local')
        ->and($database(['APP_ENV' => 'staging', 'DB_DATABASE' => null]))->toBe('uphsl_credential_tracker_staging')
        ->and($database(['APP_ENV' => 'production', 'DB_DATABASE' => null]))->toBe('uphsl_credential_tracker')
        ->and($database(['APP_ENV' => 'staging', 'DB_DATABASE' => 'custom_db']))->toBe('custom_db');
});

test('error details show on local and staging but never in production', function () {
    $debug = fn (array $vars) => configUnder('app.php', $vars)['debug'];

    expect($debug(['APP_ENV' => 'local', 'APP_DEBUG' => null]))->toBeTrue()
        ->and($debug(['APP_ENV' => 'staging', 'APP_DEBUG' => null]))->toBeTrue()
        ->and($debug(['APP_ENV' => 'production', 'APP_DEBUG' => null]))->toBeFalse()
        ->and($debug(['APP_ENV' => 'production', 'APP_DEBUG' => 'true']))->toBeFalse();
});

test('every environment sends real email once Gmail is configured, and logs it otherwise', function () {
    $mailer = fn (array $vars) => configUnder('mail.php', ['MAIL_MAILER' => null, ...$vars])['default'];

    foreach (['local', 'staging', 'production'] as $env) {
        expect($mailer(['APP_ENV' => $env, 'MAIL_USERNAME' => 'tracker@gmail.com']))->toBe('smtp')
            ->and($mailer(['APP_ENV' => $env, 'MAIL_USERNAME' => null]))->toBe('log');
    }
});

test('only local redirects every email to the developer inbox', function () {
    $redirect = fn (string $env) => configUnder('mail.php', [
        'APP_ENV' => $env, 'MAIL_USERNAME' => 'tracker@gmail.com', 'MAIL_REDIRECT_TO' => null,
    ])['redirect_to'];

    expect($redirect('local'))->toBe('tracker@gmail.com')
        ->and($redirect('staging'))->toBeNull()
        ->and($redirect('production'))->toBeNull();
});

test('each environment has its own address unless APP_URL is set', function () {
    $url = fn (array $vars) => configUnder('app.php', $vars)['url'];

    expect($url(['APP_ENV' => 'local', 'APP_URL' => null]))->toBe('http://localhost:8000')
        ->and($url(['APP_ENV' => 'staging', 'APP_URL' => null]))->toBe('https://staging.18-139-77-227.sslip.io')
        ->and($url(['APP_ENV' => 'production', 'APP_URL' => null]))->toBe('https://18-139-77-227.sslip.io')
        ->and($url(['APP_ENV' => 'production', 'APP_URL' => 'https://tracker.example.test']))->toBe('https://tracker.example.test');
});

test('session cookies are secure exactly when the app URL is https', function () {
    $secure = function (string $url) {
        config(['app.url' => $url]);

        return configUnder('session.php', ['SESSION_SECURE_COOKIE' => null])['secure'];
    };

    expect($secure('https://tracker.example.test'))->toBeTrue()
        ->and($secure('http://localhost:8000'))->toBeFalse();
});

test('demo data cannot be reset in production', function () {
    $department = Department::factory()->create();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:reset-demo')->assertFailed();

    expect($department->fresh())->not->toBeNull();
});

test('demo data is not reset without the registrar settings, so nothing is wiped', function () {
    $department = Department::factory()->create();
    app()->detectEnvironment(fn () => 'staging');
    config(['uphsl.initial_registrar.password' => null]);

    $this->artisan('app:reset-demo')
        ->expectsOutputToContain('Nothing was changed')
        ->assertFailed();

    expect($department->fresh())->not->toBeNull();
});

test('the app refuses to start with an unknown APP_ENV', function () {
    $result = Process::path(base_path())->env(['APP_ENV' => 'demo'])->run([PHP_BINARY, 'artisan', 'about']);

    expect($result->failed())->toBeTrue()
        ->and($result->output().$result->errorOutput())->toContain('APP_ENV must be "local", "staging" or "production"');
});
