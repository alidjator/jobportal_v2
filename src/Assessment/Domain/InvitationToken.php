<?php

namespace Src\Assessment\Domain;

/** The secret in a psychologist's invitation link: 32 random bytes, shown as 43 URL-safe characters. Only its SHA-256 hash is ever stored. */
final readonly class InvitationToken
{
    public const int LENGTH = 43;

    private function __construct(public string $plain) {}

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public static function fromPlain(string $plain): self
    {
        return new self($plain);
    }

    public function hash(): string
    {
        return hash('sha256', $this->plain);
    }
}
