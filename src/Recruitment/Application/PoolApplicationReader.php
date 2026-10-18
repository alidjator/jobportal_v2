<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/** Reads what the talent pool needs of applications (`Src\Shared\Application\Recruitment\PoolApplications`); the visibility of the member is applied by the caller. */
interface PoolApplicationReader
{
    /** @return array{companyId: int, applicantUserId: int, stageType: StageType, consentGiven: bool, appliedAt: DateTimeImmutable}|null */
    public function facts(int $applicationId): ?array;

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function consentingUserIds(int $companyId, array $userIds, DateTimeImmutable $since): array;

    /**
     * The latest consenting application of each applicant submitted at or after `$since`, with its profile snapshot.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{id: int, appliedAt: DateTimeImmutable, snapshot: array<string, mixed>}> by applicant id
     */
    public function latestConsenting(int $companyId, array $userIds, DateTimeImmutable $since): array;

    /**
     * The latest application of each applicant to the company, whatever its status.
     *
     * @param  list<int>  $userIds
     * @return array<int, PoolApplicationRow> by applicant id
     */
    public function latest(int $companyId, array $userIds): array;

    /**
     * Every application of the applicant to the company, newest first.
     *
     * @return list<PoolApplicationRow>
     */
    public function of(int $companyId, int $applicantUserId): array;
}
