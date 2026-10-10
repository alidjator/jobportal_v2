<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Src\Shared\Infrastructure\Testing\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs right after the application boots and before any test trait touches the database,
     * so a misconfigured run aborts before it can reach the application database.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $config = $this->app->make('config');
        $connection = $config->string('database.default');

        // A parallel process (paratest sets TEST_TOKEN) works in its own copy of the test database.
        $token = getenv('TEST_TOKEN');

        if ($token !== false && $token !== '') {
            $config->set("database.connections.{$connection}.database", TestDatabaseGuard::databaseForProcess($token));
            $this->app->make('db')->purge($connection);
        }

        TestDatabaseGuard::assertNoDestructiveTraits(class_uses_recursive(static::class));
        TestDatabaseGuard::assertEnvironment($this->app->environment());
        TestDatabaseGuard::assertDatabase(
            $config->string("database.connections.{$connection}.database"),
            $config->string("database.connections.{$connection}.prefix"),
        );
    }
}
