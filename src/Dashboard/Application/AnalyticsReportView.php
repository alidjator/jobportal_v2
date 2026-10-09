<?php

namespace Src\Dashboard\Application;

/** An analysis ready to send: its tables after the small-group rule, the range, and when it was built (it may be a few minutes old). */
final readonly class AnalyticsReportView
{
    /** @param  list<array{name: string, columns: list<string>, count_column: string|null, rows: list<array<string, mixed>>}>  $tables */
    public function __construct(
        public string $report,
        public string $from,
        public string $to,
        public int $minGroup,
        public array $tables,
        public string $generatedAt,
    ) {}
}
