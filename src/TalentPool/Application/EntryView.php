<?php

namespace Src\TalentPool\Application;

use DateTimeImmutable;
use Src\Shared\Application\JobInvitation\LatestInvitation;
use Src\Shared\Application\Recruitment\PoolApplicationRef;
use Src\TalentPool\Domain\EntrySource;

/**
 * An entry as the company sees it. An entry is available while the candidate can be found or still carries a consent (`docs/design/phase-8-talent-pool.md` section 1);
 * otherwise nothing about the person is shown, the company's note included: the entry stays so the company can remove it. `$candidateSlug` is set only for a findable
 * candidate (the key of the candidate search); a person available through consent alone is named from the snapshot of their application, without a profile to open.
 */
final readonly class EntryView
{
    /**
     * @param  array{title: string, employer: string}|null  $latestPosition
     * @param  list<string>  $skills
     */
    public function __construct(
        public int $id,
        public ?int $categoryId,
        public ?string $categoryName,
        public bool $available,
        public ?string $candidateSlug,
        public ?string $candidateName,
        public ?string $note,
        public DateTimeImmutable $createdAt,
        public EntrySource $source,
        public ?int $savedById,
        public ?string $savedByName,
        public ?string $headline = null,
        public ?string $city = null,
        public ?array $latestPosition = null,
        public array $skills = [],
        public ?PoolApplicationRef $lastApplication = null,
        public ?LatestInvitation $lastInvitation = null,
        public bool $hasAvatar = false,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /** The latest of the last application and the last invitation, or the day the entry was saved when there is neither. */
    public function lastActivity(): DateTimeImmutable
    {
        return max($this->lastApplication->appliedAt ?? $this->createdAt, $this->lastInvitation->sentAt ?? $this->createdAt, $this->createdAt);
    }
}
