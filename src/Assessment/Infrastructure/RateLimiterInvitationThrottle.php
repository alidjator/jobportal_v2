<?php

namespace Src\Assessment\Infrastructure;

use Illuminate\Cache\RateLimiter;
use Src\Assessment\Application\Invitations\InvitationThrottle;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** At most 20 invitation lookups or acceptances per IP address in a window of one hour. A real invitee needs two. */
final readonly class RateLimiterInvitationThrottle implements InvitationThrottle
{
    public const int MAX_PER_IP = 20;

    public const int WINDOW_SECONDS = 3600;

    public function __construct(private RateLimiter $limiter) {}

    public function hit(?string $ipAddress): void
    {
        $key = 'assessment:invitation:ip:'.hash('sha256', $ipAddress ?? 'unknown');

        if ($this->limiter->tooManyAttempts($key, self::MAX_PER_IP)) {
            throw new TooManyAttempts('Too many invitation attempts. Try again later.', $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, self::WINDOW_SECONDS);
    }
}
