<?php

namespace Src\Shared\Infrastructure\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the `test_`-prefixed tables used by the test suite inside the application database.
 *
 * It only runs the pending migrations under the test prefix. It never uses `migrate:fresh`, so tables
 * without the prefix are never dropped or touched.
 */
final class MigrateTestDatabaseCommand extends Command
{
    protected $signature = 'test:database:migrate';

    protected $description = 'Create or update the test_-prefixed tables used by the test suite (never drops tables)';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->components->error('This command must not run in the production environment.');

            return self::FAILURE;
        }

        $connection = Config::string('database.default');
        $prefixKey = "database.connections.{$connection}.prefix";

        Config::set($prefixKey, TestDatabaseGuard::REQUIRED_PREFIX);
        DB::purge($connection);

        TestDatabaseGuard::assertPrefix(Config::string($prefixKey));

        return $this->call('migrate', ['--force' => true]);
    }
}
