<?php

namespace Src\Shared\Domain;

use DateTimeImmutable;

/** A fact that happened in the domain, named in the past tense (for example `EmailVerified`). */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
