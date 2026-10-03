<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests run against the `test_`-prefixed tables created by `php artisan test:database:migrate`;
 * every test is wrapped in a transaction that is rolled back. Never use `RefreshDatabase` here: it runs
 * `migrate:fresh`, which drops every table in the shared database, not only the test tables.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;
}
