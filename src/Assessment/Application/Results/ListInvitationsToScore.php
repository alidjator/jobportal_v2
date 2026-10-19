<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Catalog\CatalogAccess;
use Src\Assessment\Application\Delivery\InvitationViews;
use Src\Assessment\Domain\AwaitingScoring;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The tests waiting for the member to score (`docs/design/phase-8-recruiter-workday.md`, the `my_scoring` task): submitted tests of the company with a long-text answer
 * and the member among the assessors, the longest waiting first. Needs no permission beyond being an assessor, like the task.
 */
final readonly class ListInvitationsToScore
{
    public function __construct(
        private CatalogAccess $access,
        private InvitationRepository $invitations,
        private InvitationViews $views,
        private ApplicantContacts $applicants,
        private JobDirectory $jobs,
    ) {}

    /**
     * @return Page<ScoringQueueEntry>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, PageRequest $request): Page
    {
        $companyId = $this->access->companyId($actor);
        $page = $this->invitations->pageAwaitingScoring($companyId, (int) $actor->id, $request);
        $invitations = array_map(static fn (AwaitingScoring $row) => $row->invitation, $page->items);
        $views = $this->views->many($invitations);
        $names = $this->applicants->names(array_values(array_unique(array_map(static fn ($invitation): int => $invitation->applicantUserId, $invitations))));
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn ($invitation): int => $invitation->jobId, $invitations))));

        $entries = [];

        foreach ($page->items as $index => $row) {
            $entries[] = new ScoringQueueEntry($views[$index], $names[$row->invitation->applicantUserId] ?? null, $titles[$row->invitation->jobId] ?? '', $row->submittedAt);
        }

        return new Page($entries, $page->total, $page->request);
    }
}
