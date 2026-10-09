<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** The log of what company members opened. One entry per member, candidate, kind and day. */
interface ProfileViewRepository
{
    public function exists(int $memberId, int $userId, ProfileViewKind $kind, DateTimeImmutable $day): bool;

    /** Writes the entry unless the member already has one for that day; true when it was new. */
    public function record(int $userId, int $companyId, int $memberId, ProfileViewKind $kind, DateTimeImmutable $day, DateTimeImmutable $at): bool;

    /** How many entries of the kind the member has for the day. */
    public function countOfMember(int $memberId, ProfileViewKind $kind, DateTimeImmutable $day): int;

    /**
     * The companies that opened the candidate's profile or resumes, newest day first: one row per company, kind and day (members of one company are not told apart).
     *
     * @return Page<array{company_id: int, kind: ProfileViewKind, day: string, viewed_at: DateTimeImmutable}>
     */
    public function ofCandidate(int $userId, PageRequest $request): Page;
}
