<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

interface ProfileRepository
{
    public function find(int $userId): ?JobSeekerProfile;

    /** Writes the profile, creating its row the first time. */
    public function save(JobSeekerProfile $profile, DateTimeImmutable $at): void;

    /**
     * Locks the user's profile row until the transaction ends, so changes that count entries (a limit on a list) run one after the other. The row is
     * created empty when the user has none yet; an existing profile is left as it is.
     */
    public function lock(int $userId, DateTimeImmutable $at): void;

    /**
     * How many entries the user has in the lists that decide whether the profile is complete.
     *
     * @return array{experiences: int, educations: int, resumes: int}
     */
    public function counts(int $userId): array;
}
