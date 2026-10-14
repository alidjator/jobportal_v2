<?php

namespace Src\Assessment\Application\Attempts;

/**
 * The token of the device that sends the request (header `X-Attempt-Session`), read by the HTTP layer and checked by `AttemptGate`, so the use cases do not pass it
 * along. It lives for one request. A token is random; only its hash is kept with the attempt.
 */
final class AttemptSessionToken
{
    private ?string $value = null;

    public function set(?string $value): void
    {
        $this->value = $value === '' ? null : $value;
    }

    public function hash(): ?string
    {
        return $this->value === null ? null : self::hashOf($this->value);
    }

    /** @return array{0: string, 1: string} a new token and its hash */
    public static function issue(): array
    {
        $token = bin2hex(random_bytes(20));

        return [$token, self::hashOf($token)];
    }

    private static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }
}
