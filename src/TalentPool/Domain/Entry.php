<?php

namespace Src\TalentPool\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A candidate a company saved, with the category it put them in (none when uncategorised) and an optional plain-text note of up to 500 characters. `source` says how
 * it was saved; `applicationId` is the consenting application it was saved from and `savedByUserId` the member who saved it (both null for older entries).
 */
final readonly class Entry
{
    public const int MAX_NOTE_LENGTH = 500;

    public function __construct(
        public int $companyId,
        public int $candidateUserId,
        public ?int $categoryId,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
        public ?string $note = null,
        public EntrySource $source = EntrySource::Search,
        public ?int $applicationId = null,
        public ?int $savedByUserId = null,
    ) {}

    /**
     * A note as it is stored: trimmed, and null when it is empty.
     *
     * @throws BusinessRuleViolation `note-too-long`
     */
    public static function cleanNote(?string $note): ?string
    {
        $note = $note === null ? '' : trim($note);

        if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new BusinessRuleViolation('A note has at most '.self::MAX_NOTE_LENGTH.' characters.', 'note-too-long');
        }

        return $note === '' ? null : $note;
    }
}
