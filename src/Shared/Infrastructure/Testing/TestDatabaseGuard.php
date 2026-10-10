<?php

namespace Src\Shared\Infrastructure\Testing;

use RuntimeException;

/**
 * Fails closed unless the connection points at the dedicated test database.
 *
 * The tests run in their own database, never in the application database, so a misconfigured run
 * would otherwise create, change or drop real tables.
 */
final class TestDatabaseGuard
{
    public const REQUIRED_DATABASE = 'jobportal_v2_test';

    public const REQUIRED_ENVIRONMENT = 'testing';

    /** A parallel test process works in its own copy, named after the dedicated database and the process token. */
    private const PROCESS_DATABASE_PATTERN = '/^jobportal_v2_test_[1-9][0-9]*$/';

    public static function isTestDatabase(?string $database): bool
    {
        return $database === self::REQUIRED_DATABASE
            || preg_match(self::PROCESS_DATABASE_PATTERN, (string) $database) === 1;
    }

    /** The database a parallel test process uses: its own copy for a token, the dedicated database without one. */
    public static function databaseForProcess(string|false|null $token): string
    {
        return in_array($token, [null, false, '', '0'], true) ? self::REQUIRED_DATABASE : self::REQUIRED_DATABASE.'_'.(int) $token;
    }

    public static function assertDatabase(?string $database, ?string $prefix): void
    {
        if (! self::isTestDatabase($database)) {
            throw new RuntimeException(sprintf(
                'Refusing to continue: the database must be "%s" or one of its process copies, got "%s".',
                self::REQUIRED_DATABASE,
                $database ?? '',
            ));
        }

        if (($prefix ?? '') !== '') {
            throw new RuntimeException(sprintf(
                'Refusing to continue: the test database uses no table prefix, got "%s".',
                $prefix,
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
                'Refusing to continue: %s runs migrate:fresh, which drops every table of the database.',
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
