<?php

namespace Src\TalentPool\Application;

use DateTimeImmutable;

/**
 * An entry as the company sees it. While the candidate can still be found, `$candidateSlug` and `$candidateName` name them; once they switched their profile
 * off, closed the account or the profile is no longer complete, both are null and nothing else about them is shown, the company's note included: the entry
 * stays so the company can remove it.
 */
final readonly class EntryView
{
    public function __construct(
        public int $id,
        public ?int $categoryId,
        public ?string $categoryName,
        public ?string $candidateSlug,
        public ?string $candidateName,
        public ?string $note,
        public DateTimeImmutable $createdAt,
    ) {}

    public function isAvailable(): bool
    {
        return $this->candidateSlug !== null;
    }
}
