<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The companies that opened the caller's profile or resumes, newest first. */
final readonly class ListMyProfileViews
{
    public function __construct(private JobSeekerGuard $guard, private ProfileViewRepository $views, private CompanyDirectory $companies) {}

    /**
     * @return Page<ProfileViewEntry>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, PageRequest $request): Page
    {
        $page = $this->views->ofCandidate($this->guard->userId($actor), $request);
        $summaries = $this->companies->summaries(array_values(array_unique(array_map(static fn (array $row): int => $row['company_id'], $page->items))));

        return new Page(array_map(static fn (array $row): ProfileViewEntry => new ProfileViewEntry(
            $row['company_id'], isset($summaries[$row['company_id']]) ? $summaries[$row['company_id']]->name : null, $row['kind']->value, $row['day'], $row['viewed_at'],
        ), $page->items), $page->total, $page->request);
    }
}
