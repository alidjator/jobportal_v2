<?php

namespace Src\Review\Domain;

/**
 * The figures shown above a company's reviews. `count` is always given; an average is given only from {@see MINIMUM} ratings, so one person never sets the
 * company's score. An aspect average needs that many ratings of that aspect.
 */
final readonly class ReviewSummary
{
    public const int MINIMUM = 3;

    public function __construct(
        public int $count,
        public ?float $average,
        public ?float $culture,
        public ?float $pay,
        public ?float $hiring,
    ) {}

    /**
     * @param  array{culture: array{int, float|null}, pay: array{int, float|null}, hiring: array{int, float|null}}  $aspects  count and average of each aspect
     */
    public static function from(int $count, ?float $average, array $aspects): self
    {
        $shown = static fn (int $n, ?float $mean): ?float => $n >= self::MINIMUM && $mean !== null ? round($mean, 2) : null;

        return new self(
            $count,
            $shown($count, $average),
            $shown(...$aspects['culture']),
            $shown(...$aspects['pay']),
            $shown(...$aspects['hiring']),
        );
    }
}
