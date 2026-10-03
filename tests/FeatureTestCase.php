<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature test memakai database MySQL berisi skema lama
 * (`database/schema/mysql-schema.sql`); tiap test dibungkus transaksi.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;
}
