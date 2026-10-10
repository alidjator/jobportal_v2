<?php

namespace Src\Review\Interface\Http\Resources;

use Src\Review\Domain\ReviewSummary;

/** The `summary` next to a list of reviews: the count, and the averages (null below 3 ratings). */
final class ReviewSummaryPayload
{
    /** @return array{count: int, average: float|null, aspects: array{culture: float|null, pay: float|null, hiring: float|null}} */
    public static function of(ReviewSummary $summary): array
    {
        return [
            'count' => $summary->count,
            'average' => $summary->average,
            'aspects' => ['culture' => $summary->culture, 'pay' => $summary->pay, 'hiring' => $summary->hiring],
        ];
    }
}
