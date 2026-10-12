<?php

namespace Src\Recruitment\Domain\Video;

use DateInterval;
use DateTimeImmutable;

/**
 * The video room of one interview and the state of its lobby. The room is created at the first join and closed after the join window; the candidate counts
 * as waiting for {@see self::WAITING_SECONDS} seconds after the last time they asked to enter.
 */
final readonly class VideoRoom
{
    public const int WAITING_SECONDS = 60;

    public function __construct(
        public int $interviewId,
        public ?int $roomId,
        public ?string $pin,
        public ?string $secret,
        public ?DateTimeImmutable $closedAt,
        public ?DateTimeImmutable $candidateWaitingAt,
        public ?DateTimeImmutable $admittedAt,
        public ?int $admittedBy,
    ) {}

    public function isOpen(): bool
    {
        return $this->roomId !== null && $this->closedAt === null;
    }

    public function isAdmitted(): bool
    {
        return $this->admittedAt !== null;
    }

    public function isWaiting(DateTimeImmutable $now): bool
    {
        return ! $this->isAdmitted()
            && $this->candidateWaitingAt !== null
            && $this->candidateWaitingAt >= $now->sub(new DateInterval('PT'.self::WAITING_SECONDS.'S'));
    }
}
