<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** Remembers when each job seeker had a CV generated, for the hourly limit. */
interface CvGenerationRepository
{
    public function countSince(int $userId, DateTimeImmutable $since): int;

    /** Records a generation and deletes this user's records older than `$keepFrom`. */
    public function record(int $userId, DateTimeImmutable $at, DateTimeImmutable $keepFrom): void;
}
