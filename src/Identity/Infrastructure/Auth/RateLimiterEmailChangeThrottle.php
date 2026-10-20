<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Cache\RateLimiter;
use Src\Identity\Application\EmailChange\EmailChangeThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

final readonly class RateLimiterEmailChangeThrottle implements EmailChangeThrottle
{
    public const int MAX_REQUESTS_PER_HOUR = 3;

    public const int CHANGE_SECONDS = 86400;

    public function __construct(private RateLimiter $limiter) {}

    public function hitRequest(int $userId): void
    {
        $key = "identity:email-change:request:{$userId}";

        if ($this->limiter->tooManyAttempts($key, self::MAX_REQUESTS_PER_HOUR)) {
            throw new TooManyAttempts('Too many email change requests. Try again later.', $this->limiter->availableIn($key), 'email-change-limit-reached');
        }

        $this->limiter->hit($key, 3600);
    }

    public function assertNotChangedRecently(int $userId): void
    {
        $key = "identity:email-change:done:{$userId}";

        if ($this->limiter->tooManyAttempts($key, 1)) {
            throw new TooManyAttempts('The email address was changed recently. Try again later.', $this->limiter->availableIn($key), 'email-change-too-soon');
        }
    }

    public function recordChange(int $userId): void
    {
        $this->limiter->hit("identity:email-change:done:{$userId}", self::CHANGE_SECONDS);
    }
}
