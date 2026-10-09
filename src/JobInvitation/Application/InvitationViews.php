<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;

/** Turns invitations into what each side may see, looking the names up once per page. */
final readonly class InvitationViews
{
    public function __construct(
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CandidateDirectory $candidates,
        private CompanyMemberAccounts $members,
    ) {}

    /**
     * What the company sees: the candidate and the member who invited.
     *
     * @param  Page<Invitation>  $page
     * @return Page<InvitationView>
     */
    public function forCompany(Page $page): Page
    {
        $invitations = $page->items;
        $candidates = $this->candidates->refs(array_map(static fn (Invitation $i): int => $i->userId, $invitations));
        $members = $this->members->summaries(array_values(array_unique(array_map(static fn (Invitation $i): int => $i->invitedByUserId, $invitations))));
        $live = $this->liveJobs($invitations);
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (Invitation $i): int => $i->jobId, $invitations))));

        return new Page(array_map(fn (Invitation $i): InvitationView => $this->view(
            $i, $titles[$i->jobId] ?? null, null, $candidates[$i->userId]->slug ?? null, $candidates[$i->userId]->name ?? null, $i->invitedByUserId, $members[$i->invitedByUserId]->name ?? null, $live,
        ), $invitations), $page->total, $page->request);
    }

    /**
     * What the candidate sees: the company and the job, never the member who invited.
     *
     * @param  Page<Invitation>  $page
     * @return Page<InvitationView>
     */
    public function forCandidate(Page $page): Page
    {
        $invitations = $page->items;
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (Invitation $i): int => $i->companyId, $invitations))));
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (Invitation $i): int => $i->jobId, $invitations))));
        $live = $this->liveJobs($invitations);

        return new Page(array_map(fn (Invitation $i): InvitationView => $this->view(
            $i, $titles[$i->jobId] ?? null, $companies[$i->companyId]->name ?? null, null, null, null, null, $live,
        ), $invitations), $page->total, $page->request);
    }

    /**
     * @param  list<int>  $liveJobIds
     */
    private function view(Invitation $i, ?string $jobTitle, ?string $companyName, ?string $candidateSlug, ?string $candidateName, ?int $invitedBy, ?string $invitedByName, array $liveJobIds): InvitationView
    {
        return new InvitationView(
            (int) $i->id, $i->jobId, $jobTitle, $i->companyId, $companyName, $candidateSlug, $candidateName, $invitedBy, $invitedByName, $i->note, $i->status,
            $i->status->isOpen() && ! in_array($i->jobId, $liveJobIds, true), $i->declineReason, $i->viewedAt, $i->respondedAt, $i->applicationId, $i->createdAt,
        );
    }

    /**
     * @param  list<Invitation>  $invitations
     * @return list<int>
     */
    private function liveJobs(array $invitations): array
    {
        return $this->jobs->liveJobIds(array_values(array_unique(array_map(static fn (Invitation $i): int => $i->jobId, $invitations))));
    }
}
