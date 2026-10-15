<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Src\Shared\Application\Settings\Settings;
use Tests\Support\SettingsWithoutUnverifiedReview;

/**
 * Feature tests run against the dedicated test database created by `php artisan test:database:migrate --env=testing`;
 * every test is wrapped in a transaction that is rolled back. Never use `RefreshDatabase` here: it runs
 * `migrate:fresh`, which drops every table of the database.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;

    /** Whether `jobs.approval_for_unverified` applies in this test; off by default so a job of an unverified test company goes live as before. */
    protected bool $unverifiedReview = false;

    /** No test reaches the real Have I Been Pwned service: by default no password is known to be breached. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        $this->app->extend(Settings::class, fn (Settings $settings): Settings => new SettingsWithoutUnverifiedReview($settings, fn (): bool => $this->unverifiedReview));
    }
}
