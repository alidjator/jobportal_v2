<?php

namespace Src\Shared\Application\Analytics;

use Src\Shared\Application\Dashboard\DashboardAudience;

/**
 * What a report may look at: the whole platform (admin) or one company, over the Jakarta days `from` to `to` (`Y-m-d`, both included, never later than yesterday).
 * `minGroup` is the smallest group of people a report may show.
 */
final readonly class AnalyticsScope
{
    public function __construct(
        public DashboardAudience $audience,
        public ?int $companyId,
        public string $from,
        public string $to,
        public int $minGroup,
    ) {}

    public function isPlatform(): bool
    {
        return $this->companyId === null;
    }
}
