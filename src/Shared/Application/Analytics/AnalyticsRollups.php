<?php

namespace Src\Shared\Application\Analytics;

use InvalidArgumentException;

/** The daily summaries of the platform. Each context registers its own from its service provider. */
final class AnalyticsRollups
{
    /** @var array<string, AnalyticsRollup> */
    private array $rollups = [];

    public function add(AnalyticsRollup ...$rollups): void
    {
        foreach ($rollups as $rollup) {
            if (isset($this->rollups[$rollup->name()]) && ! $this->rollups[$rollup->name()] instanceof $rollup) {
                throw new InvalidArgumentException("The summary {$rollup->name()} is already registered.");
            }

            $this->rollups[$rollup->name()] = $rollup;
        }
    }

    /** @return list<AnalyticsRollup> in the order of their names */
    public function all(): array
    {
        ksort($this->rollups);

        return array_values($this->rollups);
    }
}
