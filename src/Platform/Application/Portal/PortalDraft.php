<?php

namespace Src\Platform\Application\Portal;

use DateTimeImmutable;

/** A change waiting to be published: the identity (for the platform) or the whole brand (for a group or company), as the array it is stored in. */
final readonly class PortalDraft
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(public array $payload, public DateTimeImmutable $savedAt) {}
}
