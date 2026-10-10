<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Cache\RateLimiter;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Failed attempts are counted per window, never locking an account for good. A fixed key allows 5 failures in 60 seconds.
 * Password sign-in has three layers (decided 2026-10-10): 5 failures per minute per email and address, 20 per hour per email
 * over any address, and 30 per hour per address over any email.
 */
final readonly class RateLimiterSignInThrottle implements SignInThrottle
{
    public const int MAX_FAILURES = 5;

    public const int WINDOW_SECONDS = 60;

    public const int MAX_FAILURES_PER_ACCOUNT = 20;

    public const int MAX_FAILURES_PER_ADDRESS = 30;

    public const int LONG_WINDOW_SECONDS = 3600;

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

    public function assertCredentialsNotLocked(AccountScope $scope, string $email, string $ipAddress): void
    {
        $wait = 0;

        foreach ($this->layers($scope, $email, $ipAddress) as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                $wait = max($wait, $this->limiter->availableIn($key));
            }
        }

        if ($wait > 0) {
            throw new TooManyAttempts('Too many failed sign-in attempts. Try again later.', $wait);
        }
    }

    public function recordCredentialFailure(AccountScope $scope, string $email, string $ipAddress): void
    {
        foreach ($this->layers($scope, $email, $ipAddress) as [$key, , $window]) {
            $this->limiter->hit($key, $window);
        }
    }

    public function clearCredentials(AccountScope $scope, string $email, string $ipAddress): void
    {
        $this->limiter->clear($this->cacheKey($scope->value.'|'.$email.'|'.$ipAddress));
    }

    /** @return list<array{string, int, int}> cache key, failures allowed, window in seconds */
    private function layers(AccountScope $scope, string $email, string $ipAddress): array
    {
        return [
            [$this->cacheKey($scope->value.'|'.$email.'|'.$ipAddress), self::MAX_FAILURES, self::WINDOW_SECONDS],
            [$this->cacheKey('account|'.$scope->value.'|'.$email), self::MAX_FAILURES_PER_ACCOUNT, self::LONG_WINDOW_SECONDS],
            [$this->cacheKey('address|'.$ipAddress), self::MAX_FAILURES_PER_ADDRESS, self::LONG_WINDOW_SECONDS],
        ];
    }

    /** Hashed so the email address never becomes part of a cache key. */
    private function cacheKey(string $key): string
    {
        return 'identity:sign-in:'.hash('sha256', $key);
    }
}
