<?php

namespace Src\Dashboard\Infrastructure;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Src\Dashboard\Application\DashboardCache;

final readonly class LaravelDashboardCache implements DashboardCache
{
    public function __construct(private Cache $cache) {}

    public function remember(string $key, int $seconds, Closure $build): array
    {
        /** @var array<string, mixed> */
        return $this->cache->remember($key, $seconds, $build);
    }
}
