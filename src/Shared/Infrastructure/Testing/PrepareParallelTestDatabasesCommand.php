<?php

namespace Src\Shared\Infrastructure\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Gives every parallel test process its own copy of the test database (`jobportal_v2_test_1`, `_2`, ...), made with
 * `CREATE DATABASE ... TEMPLATE`, so the copies carry exactly the schema of the migrated test database.
 *
 * It runs in the `testing` environment, only ever drops or creates databases the guard accepts as process copies, and never touches
 * `jobportal_v2_test` itself. Run it again after every new migration (after `test:database:migrate --env=testing`).
 */
final class PrepareParallelTestDatabasesCommand extends Command
{
    protected $signature = 'test:database:parallel {processes=8 : How many copies to make}';

    protected $description = 'Recreate the per-process copies of the test database for parallel runs (run with --env=testing)';

    public function handle(): int
    {
        TestDatabaseGuard::assertEnvironment($this->laravel->environment());

        $connection = Config::string('database.default');

        TestDatabaseGuard::assertDatabase(
            Config::string("database.connections.{$connection}.database"),
            Config::string("database.connections.{$connection}.prefix"),
        );

        $processes = (int) $this->argument('processes');

        if ($processes < 1 || $processes > 32) {
            $this->components->error('The number of processes must be between 1 and 32.');

            return self::FAILURE;
        }

        // CREATE DATABASE cannot run while a session uses the template, so work from the maintenance database.
        Config::set('database.connections.maintenance', [...(array) Config::get("database.connections.{$connection}"), 'database' => 'postgres']);
        $maintenance = DB::connection('maintenance');

        for ($token = 1; $token <= $processes; $token++) {
            $name = TestDatabaseGuard::databaseForProcess((string) $token);

            TestDatabaseGuard::assertDatabase($name, '');

            $maintenance->statement(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
            $maintenance->statement(sprintf('CREATE DATABASE "%s" TEMPLATE "%s"', $name, TestDatabaseGuard::REQUIRED_DATABASE));
            $this->components->info("Created {$name}.");
        }

        return self::SUCCESS;
    }
}
