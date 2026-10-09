<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Dashboard\DashboardScope;

/** Builds the cards one scope may see, through the cache, and stamps the result. A card whose permission the scope lacks is left out. */
final readonly class DashboardComposer
{
    public function __construct(
        private DashboardCards $cards,
        private DashboardCache $cache,
        private Clock $clock,
    ) {}

    public function compose(DashboardScope $scope, string $cacheKey, int $seconds): DashboardView
    {
        $built = $this->cache->remember($cacheKey, $seconds, function () use ($scope): array {
            $cards = [];

            foreach ($this->cards->for($scope->audience) as $provider) {
                $permission = $provider->permission();

                if ($permission !== null && ! in_array($permission, $scope->permissions, true)) {
                    continue;
                }

                $cards[$provider->key()] = $provider->build($scope);
            }

            return ['generated_at' => $this->clock->now()->format('Y-m-d\TH:i:s\Z'), 'cards' => $cards];
        });

        /** @var array<string, array<string, mixed>> $cards */
        $cards = $built['cards'];

        return new DashboardView($cards, (string) $built['generated_at']);
    }
}
