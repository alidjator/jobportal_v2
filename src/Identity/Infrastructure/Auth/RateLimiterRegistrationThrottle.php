<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Cache\RateLimiter;
use Src\Identity\Application\Auth\RegistrationThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** At most 10 registration attempts per IP address and 3 per email address in a window of one hour. */
final readonly class RateLimiterRegistrationThrottle implements RegistrationThrottle
{
    public const int MAX_PER_IP = 10;

    public const int MAX_PER_EMAIL = 3;

    public const int WINDOW_SECONDS = 3600;

    public function __construct(private RateLimiter $limiter) {}

    public function hit(?string $ipAddress, string $email): void
    {
        $ipKey = $this->cacheKey('ip', $ipAddress ?? 'unknown');
        $emailKey = $this->cacheKey('email', $email);

        foreach ([[$ipKey, self::MAX_PER_IP], [$emailKey, self::MAX_PER_EMAIL]] as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                throw new TooManyAttempts('Too many registration attempts. Try again later.', $this->limiter->availableIn($key));
            }
        }

        $this->limiter->hit($ipKey, self::WINDOW_SECONDS);
        $this->limiter->hit($emailKey, self::WINDOW_SECONDS);
    }

    /** Hashed so an email address never becomes part of a cache key. */
    private function cacheKey(string $kind, string $value): string
    {
        return 'identity:registration:'.$kind.':'.hash('sha256', $value);
    }
}
