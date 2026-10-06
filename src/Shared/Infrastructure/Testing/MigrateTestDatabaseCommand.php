<?php

namespace Src\Shared\Infrastructure\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Creates or updates the tables of the dedicated test database by running the pending migrations.
 *
 * It must run in the `testing` environment (`--env=testing`, which loads `.env.testing`) and refuses any other
 * database than `jobportal_v2_test`. It never uses `migrate:fresh`, so nothing is dropped.
 */
final class MigrateTestDatabaseCommand extends Command
{
    protected $signature = 'test:database:migrate';

    protected $description = 'Create or update the tables of the test database (run with --env=testing; never drops tables)';

    public function handle(): int
    {
        TestDatabaseGuard::assertEnvironment($this->laravel->environment());

        $connection = Config::string('database.default');

        TestDatabaseGuard::assertDatabase(
            Config::string("database.connections.{$connection}.database"),
            Config::string("database.connections.{$connection}.prefix"),
        );

        return $this->call('migrate', ['--force' => true]);
    }
}
