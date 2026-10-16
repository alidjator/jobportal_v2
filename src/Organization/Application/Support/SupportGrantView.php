<?php

namespace Src\Organization\Application\Support;

use DateTimeImmutable;
use Src\Organization\Domain\SupportGrant;

/** A support grant with the names of the platform admin it names and of the group member who gave it, and its state at the time it was read. */
final readonly class SupportGrantView
{
    public function __construct(public SupportGrant $grant, public ?string $adminName, public ?string $grantedByName, private DateTimeImmutable $now) {}

    /** `active`, `revoked` or `expired` */
    public function status(): string
    {
        return match (true) {
            $this->grant->revokedAt !== null => 'revoked',
            $this->grant->expiresAt <= $this->now => 'expired',
            default => 'active',
        };
    }
}
