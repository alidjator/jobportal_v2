<?php

namespace Src\Job\Infrastructure;

use Illuminate\Cache\RateLimiter;
use Src\Job\Application\Browse\SearchThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** At most 60 searches per client address in a window of one minute. */
final readonly class RateLimiterSearchThrottle implements SearchThrottle
{
    public const int MAX_PER_IP = 60;

    public const int WINDOW_SECONDS = 60;

    public function __construct(private RateLimiter $limiter) {}

    public function hit(?string $ipAddress): void
    {
        $key = 'job:search:'.hash('sha256', $ipAddress ?? 'unknown');

        if ($this->limiter->tooManyAttempts($key, self::MAX_PER_IP)) {
            throw new TooManyAttempts('Too many searches. Try again in a moment.', $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, self::WINDOW_SECONDS);
    }
}
