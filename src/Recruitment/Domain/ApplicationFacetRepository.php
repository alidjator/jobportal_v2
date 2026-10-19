<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface ApplicationFacetRepository
{
    /** Stores the facets of an application, replacing the ones it had. */
    public function save(int $applicationId, int $companyId, int $jobId, DateTimeImmutable $appliedAt, ApplicationFacets $facets): void;

    /**
     * Whether the person applied to the company before this moment (`$exceptApplicationId` is the application itself when it exists already: of two applications made
     * at the same moment, the later one has the earlier one behind it).
     */
    public function hasEarlierApplication(int $userId, int $companyId, DateTimeImmutable $before, ?int $exceptApplicationId = null): bool;

    /**
     * The next applications after `$afterId` that still have a profile snapshot and no facets, oldest id first.
     *
     * @return list<FacetSource>
     */
    public function sourcesWithoutFacets(int $afterId, int $limit): array;
}
