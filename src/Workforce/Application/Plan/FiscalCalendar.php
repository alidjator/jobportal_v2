<?php

namespace Src\Workforce\Application\Plan;

use DateTimeImmutable;
use Src\Shared\Application\Settings\Settings;
use Src\Workforce\Application\WorkforceSettings;

/** The fiscal year of a group: it starts in the month the group set (January by default) of the calendar year that names it, and runs twelve months. */
final readonly class FiscalCalendar
{
    public function __construct(private Settings $settings) {}

    /**
     * The first and the last day of the fiscal year; the start month is the group's, read through one of its tenants (the default without one).
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function period(int $fiscalYear, ?int $companyId): array
    {
        $month = $this->settings->integer(WorkforceSettings::FISCAL_YEAR_START_MONTH, $companyId);
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $fiscalYear, $month));

        return [$start, $start->modify('+1 year')->modify('-1 day')];
    }

    /** The fiscal year (named by the calendar year it starts in) that holds the day. */
    public function yearOf(DateTimeImmutable $day, ?int $companyId): int
    {
        $month = $this->settings->integer(WorkforceSettings::FISCAL_YEAR_START_MONTH, $companyId);

        return (int) $day->format('Y') - ((int) $day->format('n') < $month ? 1 : 0);
    }
}
