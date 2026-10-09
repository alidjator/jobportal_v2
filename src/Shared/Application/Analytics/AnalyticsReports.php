<?php

namespace Src\Shared\Application\Analytics;

use InvalidArgumentException;
use Src\Shared\Application\Dashboard\DashboardAudience;

/** The analyses of the platform. Each context registers its own from its service provider. */
final class AnalyticsReports
{
    /** @var array<string, AnalyticsReportProvider> keyed by `<audience>:<key>` */
    private array $providers = [];

    public function add(AnalyticsReportProvider ...$providers): void
    {
        foreach ($providers as $provider) {
            foreach ($provider->audiences() as $audience) {
                $id = $audience->value.':'.$provider->key();

                if (isset($this->providers[$id]) && ! $this->providers[$id] instanceof $provider) {
                    throw new InvalidArgumentException("The analysis {$id} is already registered.");
                }

                $this->providers[$id] = $provider;
            }
        }
    }

    public function find(DashboardAudience $audience, string $key): ?AnalyticsReportProvider
    {
        return $this->providers[$audience->value.':'.$key] ?? null;
    }

    /** @return list<string> the keys an audience may ask for, sorted */
    public function keysFor(DashboardAudience $audience): array
    {
        $keys = [];

        foreach ($this->providers as $id => $provider) {
            if (str_starts_with($id, $audience->value.':')) {
                $keys[] = $provider->key();
            }
        }

        sort($keys);

        return $keys;
    }
}
