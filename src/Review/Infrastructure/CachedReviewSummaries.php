<?php

namespace Src\Review\Infrastructure;

use Illuminate\Contracts\Cache\Repository;
use Src\Review\Application\ReviewSummaries;
use Src\Review\Domain\ReviewRepository;
use Src\Review\Domain\ReviewSummary;

/** Keeps a company's summary for 10 minutes; every change to a published review drops it. */
final readonly class CachedReviewSummaries implements ReviewSummaries
{
    public const int SECONDS = 600;

    public function __construct(private ReviewRepository $reviews, private Repository $cache) {}

    public function forCompany(int $companyId): ReviewSummary
    {
        /** @var array{int, float|null, float|null, float|null, float|null}|null $stored */
        $stored = $this->cache->get($this->key($companyId));

        if ($stored !== null) {
            return new ReviewSummary(...$stored);
        }

        $summary = $this->reviews->summary($companyId);
        $this->cache->put($this->key($companyId), [$summary->count, $summary->average, $summary->culture, $summary->pay, $summary->hiring], self::SECONDS);

        return $summary;
    }

    public function forget(int $companyId): void
    {
        $this->cache->forget($this->key($companyId));
    }

    private function key(int $companyId): string
    {
        return "review-summary:{$companyId}";
    }
}
