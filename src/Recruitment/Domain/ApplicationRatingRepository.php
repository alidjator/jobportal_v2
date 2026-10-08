<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface ApplicationRatingRepository
{
    /** Gives or replaces the member's rating of the application. */
    public function set(int $applicationId, int $memberId, int $stars, DateTimeImmutable $at): void;

    public function remove(int $applicationId, int $memberId): void;

    /**
     * The stars each member gave, keyed by the member's account id.
     *
     * @return array<int, int>
     */
    public function forApplication(int $applicationId): array;
}
