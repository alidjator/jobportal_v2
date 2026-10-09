<?php

namespace Tests\Unit\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Dashboard\DashboardWindow;

final class DashboardWindowTest extends TestCase
{
    private function at(string $utc): DashboardWindow
    {
        return DashboardWindow::at(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
    }

    public function test_the_window_is_the_last_thirty_jakarta_days_today_included(): void
    {
        $window = $this->at('2026-10-05 09:00:00');

        $this->assertSame('2026-10-05', $window->today);
        $this->assertSame('2026-09-06', $window->from);
        $this->assertSame('2026-09-05 17:00:00', $window->fromUtc->format('Y-m-d H:i:s'));
    }

    public function test_late_in_the_utc_evening_it_is_already_the_next_day_in_jakarta(): void
    {
        $window = $this->at('2026-10-05 20:00:00');

        $this->assertSame('2026-10-06', $window->today);
        $this->assertSame('2026-09-07', $window->from);
    }

    public function test_the_series_has_thirty_days_with_zeros_on_the_quiet_ones(): void
    {
        $series = $this->at('2026-10-05 09:00:00')->series(['2026-10-05' => 4, '2026-09-06' => 1, '2026-08-01' => 9]);

        $this->assertCount(30, $series);
        $this->assertSame(['day' => '2026-09-06', 'count' => 1], $series[0]);
        $this->assertSame(['day' => '2026-09-07', 'count' => 0], $series[1]);
        $this->assertSame(['day' => '2026-10-05', 'count' => 4], $series[29]);
    }

    public function test_a_utc_timestamp_is_turned_into_an_iso_text_and_a_missing_one_stays_empty(): void
    {
        $this->assertSame('2026-10-05T09:00:00Z', DashboardWindow::iso('2026-10-05 09:00:00'));
        $this->assertNull(DashboardWindow::iso(null));
    }

    public function test_the_day_expression_shifts_a_column_to_jakarta_time(): void
    {
        $this->assertSame('DATE(DATE_ADD(created_at, INTERVAL 7 HOUR))', DashboardWindow::dayOf('created_at'));
    }
}
