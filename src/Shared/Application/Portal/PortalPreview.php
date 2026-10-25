<?php

namespace Src\Shared\Application\Portal;

use DateTimeImmutable;

/** A token that lets the career site show the portal as a draft would make it, until `expiresAt`. */
final readonly class PortalPreview
{
    public function __construct(public string $token, public DateTimeImmutable $expiresAt) {}
}
