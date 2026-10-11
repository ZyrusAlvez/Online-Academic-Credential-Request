<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests should not depend on a compiled front-end build.
        $this->withoutVite();
    }

    /**
     * Runs right after the app boots and before RefreshDatabase wipes the database,
     * so a misconfigured run can never touch the production database.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_staging')) {
            throw new RuntimeException("Tests must run on a *_staging database, not \"{$database}\".");
        }
    }
}
