<?php

namespace Src\Dashboard\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * The Jakarta days an analysis covers, both included. Data runs up to yesterday, so a range never reaches today. Without a start the range is the 30 days that end
 * at the end of the range; without an end it ends yesterday. At most 366 days.
 */
final readonly class AnalyticsRange
{
    public const int MAX_DAYS = 366;

    private const int DEFAULT_DAYS = 30;

    private function __construct(public string $from, public string $to) {}

    /** @throws BusinessRuleViolation `invalid-range` */
    public static function of(?string $from, ?string $to, DateTimeImmutable $now): self
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $yesterday = $now->setTimezone($zone)->setTime(0, 0)->sub(new DateInterval('P1D'))->format('Y-m-d');

        $to ??= $yesterday;
        $from ??= (new DateTimeImmutable($to, $zone))->sub(new DateInterval('P'.(self::DEFAULT_DAYS - 1).'D'))->format('Y-m-d');

        if ($to > $yesterday) {
            throw self::invalid('to', 'The range can end yesterday at the latest.');
        }

        if ($from > $to) {
            throw self::invalid('from', 'The start of the range cannot be after its end.');
        }

        if (AnalyticsDays::count($from, $to) > self::MAX_DAYS) {
            throw self::invalid('from', new ErrorMessage('The range can cover at most :max days.', ['max' => self::MAX_DAYS]));
        }

        return new self($from, $to);
    }

    private static function invalid(string $field, string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-range', extensions: ['errors' => [$field => [$message]]]);
    }
}
