<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Testing\TestDatabaseGuard;
use Tests\FeatureTestCase;

final class TestDatabaseSetupTest extends FeatureTestCase
{
    public function test_the_connection_uses_the_dedicated_test_database_without_a_prefix(): void
    {
        $this->assertSame(TestDatabaseGuard::databaseForProcess(getenv('TEST_TOKEN')), DB::connection()->getDatabaseName());
        $this->assertSame('', DB::connection()->getTablePrefix());
    }

    public function test_the_database_is_reachable_inside_a_transaction(): void
    {
        $this->assertSame(1, DB::connection()->transactionLevel());
        $this->assertSame(1, (int) DB::scalar('select 1'));
    }
}
