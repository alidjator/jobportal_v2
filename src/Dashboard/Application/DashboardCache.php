<?php

namespace Src\Dashboard\Application;

use Closure;

/** A short-lived cache of a built dashboard, so a client that refreshes often does not run the queries every time. */
interface DashboardCache
{
    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function remember(string $key, int $seconds, Closure $build): array;
}
