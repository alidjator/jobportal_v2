<?php

namespace Src\Shared\Infrastructure\Testing;

use RuntimeException;

/**
 * Fails closed unless the database connection carries the dedicated test table prefix.
 *
 * Tests share the application database with the real tables, so a missing or wrong prefix
 * would let them create, change or drop objects that do not belong to the tests.
 */
final class TestDatabaseGuard
{
    public const REQUIRED_PREFIX = 'test_';

    public const REQUIRED_ENVIRONMENT = 'testing';

    public static function assertPrefix(?string $prefix): void
    {
        if ($prefix !== self::REQUIRED_PREFIX) {
            throw new RuntimeException(sprintf(
                'Refusing to continue: the database table prefix must be "%s", got "%s".',
                self::REQUIRED_PREFIX,
                $prefix ?? '',
            ));
        }
    }

    /**
     * @param  array<string, string>  $traits  Trait names used by a test class, as returned by class_uses_recursive().
     */
    public static function assertNoDestructiveTraits(array $traits): void
    {
        $forbidden = array_intersect($traits, [
            'Illuminate\Foundation\Testing\RefreshDatabase',
            'Illuminate\Foundation\Testing\DatabaseMigrations',
        ]);

        if ($forbidden !== []) {
            throw new RuntimeException(sprintf(
                'Refusing to continue: %s runs migrate:fresh, which drops every table in the shared database.',
                implode(', ', $forbidden),
            ));
        }
    }

    public static function assertEnvironment(string $environment): void
    {
        if ($environment !== self::REQUIRED_ENVIRONMENT) {
            throw new RuntimeException(sprintf(
                'Refusing to continue: the application environment must be "%s", got "%s".',
                self::REQUIRED_ENVIRONMENT,
                $environment,
            ));
        }
    }
}
