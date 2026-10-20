<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

/**
 * What the karir.pkp import did and did not carry for one company: counts per code (and per reason), written by the import, no personal content. The text of each code and the notes that
 * are true for every imported company belong to the client (`ImportSummaryNotes` lists their codes).
 */
final readonly class ImportSummary
{
    /** @param  list<array{code: string, count: int, detail?: array<string, int>}>  $items */
    public function __construct(
        public int $companyId,
        public array $items,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $dismissedAt,
    ) {}
}
