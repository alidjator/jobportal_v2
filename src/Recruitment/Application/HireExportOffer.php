<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\OfferTerms;

/** The accepted offer of a hire, as far as the export shows it; `terms` is null for an offer sent before the terms existed. */
final readonly class HireExportOffer
{
    public function __construct(public string $position, public int $salary, public DateTimeImmutable $startDate, public DateTimeImmutable $acceptedAt, public ?OfferTerms $terms = null) {}
}
