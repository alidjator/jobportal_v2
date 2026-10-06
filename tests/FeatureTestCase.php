<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests run against the dedicated test database created by `php artisan test:database:migrate --env=testing`;
 * every test is wrapped in a transaction that is rolled back. Never use `RefreshDatabase` here: it runs
 * `migrate:fresh`, which drops every table of the database.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;
}
