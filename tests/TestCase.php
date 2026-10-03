<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Src\Shared\Infrastructure\Testing\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs right after the application boots and before any test trait touches the database,
     * so a misconfigured run aborts before it can reach tables without the test prefix.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $config = $this->app->make('config');
        $connection = $config->string('database.default');

        TestDatabaseGuard::assertNoDestructiveTraits(class_uses_recursive(static::class));
        TestDatabaseGuard::assertEnvironment($this->app->environment());
        TestDatabaseGuard::assertPrefix($config->string("database.connections.{$connection}.prefix"));
    }
}
