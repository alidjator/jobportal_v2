<?php

namespace Src\TalentPool\Domain;

use DateTimeImmutable;

/** A candidate a company saved, with the category it put them in (none when uncategorised). */
final readonly class Entry
{
    public function __construct(
        public int $companyId,
        public int $candidateUserId,
        public ?int $categoryId,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}
}
