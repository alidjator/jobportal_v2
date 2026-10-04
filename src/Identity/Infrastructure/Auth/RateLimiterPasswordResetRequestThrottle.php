<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Cache\RateLimiter;
use Src\Identity\Application\Auth\PasswordResetRequestThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** At most 10 password reset requests per IP address in a window of one hour. */
final readonly class RateLimiterPasswordResetRequestThrottle implements PasswordResetRequestThrottle
{
    public const int MAX_PER_IP = 10;

    public const int WINDOW_SECONDS = 3600;

    public function __construct(private RateLimiter $limiter) {}

    public function hit(?string $ipAddress): void
    {
        $key = 'identity:password-reset:ip:'.hash('sha256', $ipAddress ?? 'unknown');

        if ($this->limiter->tooManyAttempts($key, self::MAX_PER_IP)) {
            throw new TooManyAttempts('Too many password reset requests. Try again later.', $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, self::WINDOW_SECONDS);
    }
}
