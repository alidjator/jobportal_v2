<?php

namespace Src\Shared\Application\Analytics;

use Src\Shared\Application\Dashboard\DashboardAudience;

/** One analysis. The context that owns the data builds it from its own tables; the Dashboard context serves it. */
interface AnalyticsReportProvider
{
    /** The name in the URL, in lower case with underscores. */
    public function key(): string;

    /** @return list<DashboardAudience> */
    public function audiences(): array;

    /** @return list<AnalyticsTable> the first table is the one a CSV export gives unless another is asked for */
    public function build(AnalyticsScope $scope): array;
}
