<?php

namespace Src\Recruitment\Domain\Video;

use DateTimeImmutable;

/** When one person was in the video room: from `$joinedAt` until `$leftAt` (null while they are still there or the room closed without a leave event). */
final readonly class InterviewAttendance
{
    public const string COMPANY = 'company';

    public const string CANDIDATE = 'candidate';

    public function __construct(public int $userId, public string $role, public DateTimeImmutable $joinedAt, public ?DateTimeImmutable $leftAt = null, public ?int $sessionId = null) {}
}
