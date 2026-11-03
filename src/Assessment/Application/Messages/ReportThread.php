<?php

namespace Src\Assessment\Application\Messages;

use DateTimeImmutable;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportMessage;
use Src\Assessment\Domain\ReportMessageAuthor;

/**
 * The messages about one report as one reader sees them. `open` says whether a new message is accepted now (the report is signed and its signing is less than 30 days old); `closesOn` is the moment it stops
 * taking messages.
 */
final readonly class ReportThread
{
    /** @param  list<ReportMessageView>  $messages */
    public function __construct(public int $reportId, public array $messages, public bool $open, public ?DateTimeImmutable $closesOn) {}

    /** Whether the last message is a company's question nobody answered yet. */
    public static function isOpenQuestion(?ReportMessage $last): bool
    {
        return $last !== null && $last->author === ReportMessageAuthor::Company;
    }

    public static function closesOn(Report $report): ?DateTimeImmutable
    {
        return $report->isSigned() && $report->signedAt !== null ? $report->signedAt->modify(Report::QUESTION_WINDOW) : null;
    }
}
