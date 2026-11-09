<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertIsolatedTestDatabase();
    }

    /**
     * Refuse RefreshDatabase / migrations against the local app database.
     */
    protected function assertIsolatedTestDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Tests must run with APP_ENV=testing.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $allowed = $connection === 'sqlite' && (
            $database === ':memory:'
            || str_ends_with(str_replace('\\', '/', $database), '/database/testing.sqlite')
        );

        if (! $allowed) {
            throw new RuntimeException(
                "Refusing to run tests against non-isolated database [{$connection}:{$database}]. "
                .'Configure phpunit.xml / .env.testing to use sqlite :memory: or database/testing.sqlite.'
            );
        }
    }
}
