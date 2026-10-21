<?php

namespace Src\Content\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A platform announcement shown as a banner between `startsAt` and `endsAt` to its audience. A banner stays at most 90 days. */
final readonly class Announcement
{
    public const int MAX_DAYS = 90;

    public function __construct(
        public ?int $id,
        public string $title,
        public string $body,
        public ?string $linkUrl,
        public AnnouncementAudience $audience,
        public AnnouncementLevel $level,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?int $createdByAdminId,
    ) {}

    /** @throws BusinessRuleViolation `invalid-announcement` when it ends before it starts or stays longer than 90 days */
    public function assertValidWindow(): void
    {
        $message = match (true) {
            $this->endsAt <= $this->startsAt => 'The announcement must end after it starts.',
            $this->endsAt > $this->startsAt->modify('+'.self::MAX_DAYS.' days') => 'An announcement can stay at most :max days.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, 'invalid-announcement', extensions: ['errors' => ['ends_at' => [$message]]], parameters: ['max' => self::MAX_DAYS]);
        }
    }

    public function isLiveAt(DateTimeImmutable $now): bool
    {
        return $this->startsAt <= $now && $now < $this->endsAt;
    }

    public function status(DateTimeImmutable $now): string
    {
        return match (true) {
            $now < $this->startsAt => 'scheduled',
            $now >= $this->endsAt => 'ended',
            default => 'live',
        };
    }
}
