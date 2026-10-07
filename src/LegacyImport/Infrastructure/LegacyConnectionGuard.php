<?php

namespace Src\LegacyImport\Infrastructure;

use RuntimeException;

/** Fails closed unless the legacy connection is a different database (or table prefix) than the application's own, so an import can never read from the place it writes to. */
final class LegacyConnectionGuard
{
    /**
     * @param  array<string, mixed>  $legacy  the `legacy` connection config
     * @param  array<string, mixed>  $application  the default connection config
     */
    public static function assertSafe(array $legacy, array $application): void
    {
        if (($legacy['database'] ?? '') === '' || ($legacy['username'] ?? '') === '') {
            throw new RuntimeException('The legacy connection is not configured: set KARIR_PKP_DB_DATABASE and KARIR_PKP_DB_USERNAME (a read-only account).');
        }

        $same = ($legacy['host'] ?? null) === ($application['host'] ?? null)
            && ($legacy['port'] ?? null) === ($application['port'] ?? null)
            && ($legacy['database'] ?? null) === ($application['database'] ?? null)
            && ($legacy['prefix'] ?? '') === ($application['prefix'] ?? '');

        if ($same) {
            throw new RuntimeException('Refusing to continue: the legacy connection points at the same database and table prefix as the application.');
        }
    }
}
