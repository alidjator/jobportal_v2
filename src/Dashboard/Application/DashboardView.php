<?php

namespace Src\Dashboard\Application;

/** A dashboard: its cards by key, and when they were built (they may be a few minutes old, see the cache lifetime of each dashboard). */
final readonly class DashboardView
{
    /** @param  array<string, array<string, mixed>>  $cards */
    public function __construct(
        public array $cards,
        public string $generatedAt,
    ) {}
}
