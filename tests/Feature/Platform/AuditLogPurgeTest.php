<?php

namespace Tests\Feature\Platform;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class AuditLogPurgeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function entry(string $time, string $action = 'auth.login'): void
    {
        DB::table('platform_audit_logs')->insert(['actor_type' => 'user', 'actor_id' => 1, 'action' => $action, 'occurred_at' => $time]);
    }

    public function test_entries_older_than_twelve_months_are_deleted_and_newer_ones_kept(): void
    {
        $this->entry('2025-09-01 00:00:00.000000');
        $this->entry('2025-10-03 02:59:59.999999');
        $this->entry('2025-10-03 03:00:00.000000');
        $this->entry('2026-05-01 00:00:00.000000');

        $this->assertSame(0, Artisan::call('platform:purge-audit-logs'));

        $remaining = DB::table('platform_audit_logs')->where('action', 'auth.login')->orderBy('occurred_at')->pluck('occurred_at')->all();
        $this->assertSame(['2025-10-03 03:00:00.000000', '2026-05-01 00:00:00.000000'], $remaining);
    }

    public function test_the_purge_records_itself_with_the_number_deleted_and_the_cutoff(): void
    {
        $this->entry('2025-01-01 00:00:00.000000');
        $this->entry('2025-02-01 00:00:00.000000');

        $this->assertStringContainsString('Deleted 2 audit log entries.', $this->runPurge());

        $purged = DB::table('platform_audit_logs')->where('action', 'audit.purged')->get();
        $this->assertCount(1, $purged);
        $row = $purged[0];
        $this->assertSame('system', $row->actor_type);
        $this->assertNull($row->actor_id);
        $this->assertSame(['deleted' => 2, 'older_than' => '2025-10-03T03:00:00+00:00'], json_decode($row->metadata, true));
        $this->assertStringStartsWith('2026-10-03 03:00:00', $row->occurred_at);
    }

    public function test_nothing_is_recorded_when_nothing_was_old_enough(): void
    {
        $this->entry('2026-09-01 00:00:00.000000');

        $this->assertStringContainsString('Deleted 0 audit log entries.', $this->runPurge());

        $this->assertSame(1, DB::table('platform_audit_logs')->count());
    }

    public function test_running_it_again_deletes_nothing_more_and_keeps_its_own_record(): void
    {
        $this->entry('2025-01-01 00:00:00.000000');
        $this->runPurge();
        $this->runPurge();

        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'audit.purged')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->count());
    }

    public function test_the_purge_record_is_itself_purged_twelve_months_later(): void
    {
        $this->entry('2025-01-01 00:00:00.000000');
        $this->runPurge();

        Carbon::setTestNow('2027-10-03 03:00:01');
        $this->assertStringContainsString('Deleted 1 audit log entry.', $this->runPurge());

        $this->assertSame(['audit.purged'], DB::table('platform_audit_logs')->pluck('action')->all());
        $this->assertStringStartsWith('2027-10-03 03:00:01', DB::table('platform_audit_logs')->value('occurred_at'));
    }

    public function test_more_than_one_batch_is_deleted_in_one_run(): void
    {
        $rows = [];

        foreach (range(1, 2500) as $number) {
            $rows[] = ['actor_type' => 'user', 'actor_id' => $number, 'action' => 'auth.login', 'occurred_at' => '2025-01-01 00:00:00.000000'];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('platform_audit_logs')->insert($chunk);
        }

        $this->entry('2026-09-01 00:00:00.000000');

        $this->assertStringContainsString('Deleted 2500 audit log entries.', $this->runPurge());

        $this->assertSame(2, DB::table('platform_audit_logs')->count());
    }

    public function test_the_purge_is_scheduled_daily_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'platform:purge-audit-logs'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertTrue($events->first()->onOneServer);
    }

    private function runPurge(): string
    {
        Artisan::call('platform:purge-audit-logs');

        return Artisan::output();
    }
}
