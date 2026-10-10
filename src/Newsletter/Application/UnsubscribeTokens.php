<?php

namespace Src\Newsletter\Application;

/** The secret of an unsubscribe link: worked out from the subscriber id and a server key, so it is never stored and never expires. */
interface UnsubscribeTokens
{
    public function forSubscriber(int $subscriberId): string;

    /** The subscriber id the token belongs to, or null when it is not genuine. */
    public function subscriberOf(string $token): ?int;
}
