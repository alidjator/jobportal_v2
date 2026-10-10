<?php

namespace Src\Newsletter\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Src\Newsletter\Application\UnsubscribeTokens;

/** `{subscriber id}.{HMAC-SHA256 of the id, keyed with the application key}`. Nothing is stored, a changed id or a guessed signature never verifies. */
final readonly class HmacUnsubscribeTokens implements UnsubscribeTokens
{
    private const string CONTEXT = 'newsletter-unsubscribe|';

    public function __construct(private Repository $config) {}

    public function forSubscriber(int $subscriberId): string
    {
        return $subscriberId.'.'.$this->signature($subscriberId);
    }

    public function subscriberOf(string $token): ?int
    {
        if (preg_match('/^([1-9][0-9]{0,18})\.([0-9a-f]{64})$/', $token, $parts) !== 1) {
            return null;
        }

        return hash_equals($this->signature((int) $parts[1]), $parts[2]) ? (int) $parts[1] : null;
    }

    private function signature(int $subscriberId): string
    {
        return hash_hmac('sha256', self::CONTEXT.$subscriberId, (string) $this->config->get('app.key'));
    }
}
