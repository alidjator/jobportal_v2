<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class NewsletterSubscribersImporterTest extends FeatureTestCase
{
    use UsesLegacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->setUpLegacyDatabase();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(bool $write = true): ImportReport
    {
        return $this->app->make(ImportRunner::class)->run('newsletter-subscribers', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['newsletter-subscribers'];
    }

    /** @return list<string> */
    private function skipReasons(ImportReport $report): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report)['skipped_rows']);
    }

    private function email(string $address, ?string $createdAt = '2025-03-04 10:00:00', ?string $updatedAt = '2025-03-05 11:00:00'): int
    {
        return $this->legacyRow('emails', ['email' => $address, 'created_at' => $createdAt, 'updated_at' => $updatedAt]);
    }

    public function test_an_address_becomes_a_confirmed_subscriber_in_lower_case(): void
    {
        $this->email(' Budi@Example.Test ');

        $report = $this->import();

        $this->assertSame(1, $this->entity($report)['imported']);
        $row = DB::table('newsletter_subscribers')->first();
        $this->assertSame('budi@example.test', $row->email);
        $this->assertSame('confirmed', $row->status);
        $this->assertSame('id', $row->locale);
        $this->assertSame('2025-03-04 10:00:00', $row->confirmed_at);
        $this->assertSame('2025-03-05 11:00:00', $row->updated_at);
        $this->assertNull($row->unsubscribed_at);
        $this->assertNull($row->confirm_token_hash);
    }

    public function test_missing_times_fall_back_to_the_import_time(): void
    {
        $this->email('budi@example.test', null, null);

        $this->import();

        $row = DB::table('newsletter_subscribers')->first();
        $this->assertSame('2026-10-10 09:00:00', $row->confirmed_at);
        $this->assertSame('2026-10-10 09:00:00', $row->updated_at);
    }

    public function test_invalid_and_duplicate_addresses_are_skipped_and_the_oldest_wins(): void
    {
        $this->email('anna@example.test', '2025-01-01 00:00:00');
        $this->email('ANNA@example.test', '2025-02-01 00:00:00');
        $this->email('not-an-email');
        $this->email('   ');

        $report = $this->import();

        $this->assertSame(1, $this->entity($report)['imported']);
        $this->assertSame(['email-taken', 'email-invalid', 'email-missing'], $this->skipReasons($report));
        $this->assertSame(['2025-01-01 00:00:00'], DB::table('newsletter_subscribers')->pluck('confirmed_at')->all());
    }

    public function test_an_address_already_on_the_list_is_not_replaced(): void
    {
        DB::table('newsletter_subscribers')->insert(['email' => 'anna@example.test', 'status' => 'unsubscribed', 'locale' => 'en', 'unsubscribed_at' => '2026-01-01 00:00:00', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $this->email('anna@example.test');

        $report = $this->import();

        $this->assertSame(['email-taken'], $this->skipReasons($report));
        $this->assertSame('unsubscribed', DB::table('newsletter_subscribers')->value('status'));
    }

    public function test_a_dry_run_writes_nothing_and_a_second_run_adds_nothing(): void
    {
        $this->email('anna@example.test');

        $dry = $this->import(false);
        $this->assertSame(1, $this->entity($dry)['imported']);
        $this->assertSame(0, DB::table('newsletter_subscribers')->count());

        $this->import();
        $second = $this->import();

        $this->assertSame(0, $this->entity($second)['imported']);
        $this->assertSame(1, DB::table('newsletter_subscribers')->count());
    }
}
