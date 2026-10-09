<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\JobAlert\SavedSearches;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The live jobs a job alert looks for right now: the public search with the filters of the job seeker's own alert, same rows and paging. */
final readonly class SearchJobsOfAlert
{
    public function __construct(private SavedSearches $alerts, private SearchJobs $search) {}

    /**
     * @return Page<PublicJobListItem>
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the alert is missing or someone else's
     * @throws TooManyAttempts
     * @throws BusinessRuleViolation `page-out-of-range`
     */
    public function execute(Actor $actor, int $alertId, PageRequest $page, ?string $ipAddress, string $language): Page
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has job alerts.', 'job-seeker-only');
        }

        $filters = $this->alerts->filtersOf($actor->id, $alertId) ?? throw EntityNotFound::of('Job alert', $alertId);

        return $this->search->execute(JobSearchCriteria::fromFilters($filters), $page, $ipAddress, $language);
    }
}
