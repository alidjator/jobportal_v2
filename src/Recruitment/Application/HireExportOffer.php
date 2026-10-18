<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** The accepted offer of a hire, as far as the export shows it. */
final readonly class HireExportOffer
{
    public function __construct(public string $position, public int $salary, public DateTimeImmutable $startDate, public DateTimeImmutable $acceptedAt) {}
}
