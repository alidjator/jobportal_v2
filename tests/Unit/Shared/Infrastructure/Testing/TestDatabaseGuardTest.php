<?php

namespace Tests\Unit\Shared\Infrastructure\Testing;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Shared\Infrastructure\Testing\TestDatabaseGuard;

final class TestDatabaseGuardTest extends TestCase
{
    public function test_it_accepts_the_test_database_without_a_prefix(): void
    {
        TestDatabaseGuard::assertDatabase('jobportal_v2_test', '');
        TestDatabaseGuard::assertDatabase('jobportal_v2_test', null);

        $this->addToAssertionCount(2);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function invalidDatabases(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'the application database' => ['jobportal_v2'],
            'the legacy database' => ['karir_pkp'],
            'wrong case' => ['JOBPORTAL_V2_TEST'],
            'with suffix' => ['jobportal_v2_test2'],
        ];
    }

    #[DataProvider('invalidDatabases')]
    public function test_it_rejects_any_other_database(?string $database): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertDatabase($database, '');
    }

    public function test_it_rejects_a_table_prefix(): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertDatabase('jobportal_v2_test', 'test_');
    }

    public function test_it_accepts_the_testing_environment(): void
    {
        TestDatabaseGuard::assertEnvironment('testing');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidEnvironments')]
    public function test_it_rejects_any_other_environment(string $environment): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertEnvironment($environment);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidEnvironments(): array
    {
        return [
            'local' => ['local'],
            'production' => ['production'],
            'empty' => [''],
        ];
    }

    public function test_it_accepts_transaction_based_isolation(): void
    {
        TestDatabaseGuard::assertNoDestructiveTraits([DatabaseTransactions::class => DatabaseTransactions::class]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function destructiveTraits(): array
    {
        return [
            'RefreshDatabase' => [RefreshDatabase::class],
            'DatabaseMigrations' => [DatabaseMigrations::class],
        ];
    }

    #[DataProvider('destructiveTraits')]
    public function test_it_rejects_traits_that_run_migrate_fresh(string $trait): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertNoDestructiveTraits([$trait => $trait]);
    }
}
