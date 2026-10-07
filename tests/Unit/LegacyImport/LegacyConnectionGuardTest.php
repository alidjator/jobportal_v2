<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\LegacyImport\Infrastructure\LegacyConnectionGuard;

final class LegacyConnectionGuardTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(array $overrides = []): array
    {
        return ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'karir_pkp', 'username' => 'reader', 'prefix' => '', ...$overrides];
    }

    public function test_a_separate_database_is_accepted(): void
    {
        LegacyConnectionGuard::assertSafe($this->config(), $this->config(['database' => 'jobportal_v2']));

        $this->addToAssertionCount(1);
    }

    public function test_the_same_database_with_another_prefix_is_accepted(): void
    {
        LegacyConnectionGuard::assertSafe($this->config(['database' => 'jobportal_v2_test', 'prefix' => 'legacy_']), $this->config(['database' => 'jobportal_v2_test']));

        $this->addToAssertionCount(1);
    }

    public function test_the_same_database_and_prefix_as_the_application_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('same database');

        LegacyConnectionGuard::assertSafe($this->config(), $this->config());
    }

    public function test_a_connection_that_is_not_configured_is_refused(): void
    {
        foreach ([['database' => ''], ['username' => '']] as $missing) {
            try {
                LegacyConnectionGuard::assertSafe($this->config($missing), $this->config(['database' => 'jobportal_v2']));
                $this->fail('The connection was accepted.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('not configured', $e->getMessage());
            }
        }
    }
}
