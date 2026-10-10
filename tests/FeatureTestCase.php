<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

/**
 * Feature tests run against the dedicated test database created by `php artisan test:database:migrate --env=testing`;
 * every test is wrapped in a transaction that is rolled back. Never use `RefreshDatabase` here: it runs
 * `migrate:fresh`, which drops every table of the database.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;

    /** No test reaches the real Have I Been Pwned service: by default no password is known to be breached. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }
}
