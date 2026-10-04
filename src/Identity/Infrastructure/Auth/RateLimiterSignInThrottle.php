<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Cache\RateLimiter;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** At most 5 failed attempts per key in a sliding window of 60 seconds; there is no account lockout. */
final readonly class RateLimiterSignInThrottle implements SignInThrottle
{
    public const int MAX_FAILURES = 5;

    public const int WINDOW_SECONDS = 60;

    public function __construct(private RateLimiter $limiter) {}

    public function assertNotLocked(string $key): void
    {
        $key = $this->cacheKey($key);

        if ($this->limiter->tooManyAttempts($key, self::MAX_FAILURES)) {
            throw new TooManyAttempts('Too many failed sign-in attempts. Try again later.', $this->limiter->availableIn($key));
        }
    }

    public function recordFailure(string $key): void
    {
        $this->limiter->hit($this->cacheKey($key), self::WINDOW_SECONDS);
    }

    public function clear(string $key): void
    {
        $this->limiter->clear($this->cacheKey($key));
    }

    /** Hashed so the email address never becomes part of a cache key. */
    private function cacheKey(string $key): string
    {
        return 'identity:sign-in:'.hash('sha256', $key);
    }
}
