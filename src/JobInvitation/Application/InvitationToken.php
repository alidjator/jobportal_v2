<?php

namespace Src\JobInvitation\Application;

/** The token in the link of an invitation by email: 48 random hex characters, shown once; only its hash is stored. */
final readonly class InvitationToken
{
    private function __construct(public string $plain) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(24)));
    }

    public static function of(string $plain): self
    {
        return new self($plain);
    }

    public function hash(): string
    {
        return hash('sha256', $this->plain);
    }
}
