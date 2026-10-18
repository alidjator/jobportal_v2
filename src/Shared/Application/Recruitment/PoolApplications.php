<?php

namespace Src\Shared\Application\Recruitment;

use DateTimeImmutable;
use Src\Shared\Application\Actor;

/**
 * What the talent pool reads from Recruitment (`docs/design/phase-8-talent-pool.md`), without reading its tables: the facts of an application, who still carries a
 * consent, the snapshots of the consenting applications and the applications of a person with the company. Provided by Recruitment.
 */
interface PoolApplications
{
    /** The facts of the application, whoever it belongs to (the caller checks that the member may see it); null when it does not exist. */
    public function facts(int $applicationId): ?PoolApplicationFacts;

    /**
     * Those of these applicants with a consenting application to the company that was submitted at or after `$since`.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function consentingUserIds(int $companyId, array $userIds, DateTimeImmutable $since): array;

    /**
     * The snapshot of each applicant's latest consenting application to the company submitted at or after `$since`; an applicant without one is missing.
     * `$language` (`id` or `en`) picks the names of master values in the snapshot.
     *
     * @param  list<int>  $userIds
     * @return array<int, PoolConsentSnapshot> by applicant id
     */
    public function consentSnapshots(int $companyId, array $userIds, DateTimeImmutable $since, string $language): array;

    /**
     * The latest application of each applicant to the member's company, whatever its status; an applicant without one is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, PoolApplicationRef> by applicant id
     */
    public function latestApplications(Actor $actor, array $userIds): array;

    /**
     * The applications of the applicant to the member's company that the member may see, newest first, at most twenty.
     *
     * @return list<PoolApplicationRef>
     */
    public function visibleApplications(Actor $actor, int $applicantUserId): array;
}
