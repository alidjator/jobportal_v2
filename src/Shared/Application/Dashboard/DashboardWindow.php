<?php

namespace Src\Shared\Application\Dashboard;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The last 30 Jakarta calendar days, today included: where it starts in UTC (for timestamp columns) and in days (for date columns), and how a count per day becomes a full
 * series with a zero on the quiet days. Jakarta is UTC+7 all year, so a day of a UTC timestamp is the day of `timestamp + 7 hours`.
 */
final readonly class DashboardWindow
{
    public const int DAYS = 30;

    public const int OFFSET_HOURS = 7;

    /** The SQL that turns a UTC timestamp column into its Jakarta calendar day. */
    public static function dayOf(string $column): string
    {
        return 'DATE(DATE_ADD('.$column.', INTERVAL '.self::OFFSET_HOURS.' HOUR))';
    }

    /** A UTC timestamp read from the database (`Y-m-d H:i:s`) as the ISO 8601 text the API uses. */
    public static function iso(?string $utc): ?string
    {
        return $utc === null ? null : (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private function __construct(
        public string $today,
        public string $from,
        public DateTimeImmutable $fromUtc,
    ) {}

    public static function at(DateTimeImmutable $now): self
    {
        $jakarta = new DateTimeZone('Asia/Jakarta');
        $today = $now->setTimezone($jakarta)->setTime(0, 0);
        $from = $today->sub(new DateInterval('P'.(self::DAYS - 1).'D'));

        return new self($today->format('Y-m-d'), $from->format('Y-m-d'), $from->setTimezone(new DateTimeZone('UTC')));
    }

    /**
     * @param  array<string, int>  $countsByDay  `Y-m-d` => count
     * @return list<array{day: string, count: int}>
     */
    public function series(array $countsByDay): array
    {
        $series = [];
        $day = new DateTimeImmutable($this->from, new DateTimeZone('UTC'));

        for ($i = 0; $i < self::DAYS; $i++) {
            $key = $day->format('Y-m-d');
            $series[] = ['day' => $key, 'count' => $countsByDay[$key] ?? 0];
            $day = $day->add(new DateInterval('P1D'));
        }

        return $series;
    }
}
