<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

/** A row of an effective-dated timeline: valid from a day to a day (both included), the end open for the last row. */
interface Versioned
{
    public function startsOn(): DateTimeImmutable;

    public function endsOn(): ?DateTimeImmutable;

    public function withPeriod(DateTimeImmutable $from, ?DateTimeImmutable $to): static;
}
