<?php

namespace Src\Shared\Application\Dashboard;

use InvalidArgumentException;

/** The cards of every dashboard. Each context registers its own from its service provider. */
final class DashboardCards
{
    /** @var array<string, DashboardCardProvider> keyed by `<audience>:<key>` */
    private array $providers = [];

    public function add(DashboardCardProvider ...$providers): void
    {
        foreach ($providers as $provider) {
            foreach ($provider->audiences() as $audience) {
                $id = $audience->value.':'.$provider->key();

                if (isset($this->providers[$id]) && ! $this->providers[$id] instanceof $provider) {
                    throw new InvalidArgumentException("The dashboard card {$id} is already registered.");
                }

                $this->providers[$id] = $provider;
            }
        }
    }

    /** @return list<DashboardCardProvider> the cards of a dashboard, in the order of their keys */
    public function for(DashboardAudience $audience): array
    {
        $found = [];

        foreach ($this->providers as $id => $provider) {
            if (str_starts_with($id, $audience->value.':')) {
                $found[$id] = $provider;
            }
        }

        ksort($found);

        return array_values($found);
    }

    /** @return list<string> the permissions the cards of an admin dashboard ask for, sorted */
    public function permissionsFor(DashboardAudience $audience): array
    {
        $permissions = [];

        foreach ($this->for($audience) as $provider) {
            if ($provider->permission() !== null) {
                $permissions[$provider->permission()] = true;
            }
        }

        $names = array_keys($permissions);
        sort($names);

        return $names;
    }
}
