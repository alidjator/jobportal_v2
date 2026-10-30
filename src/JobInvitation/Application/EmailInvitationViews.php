<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;

/** Turns invitations by email into what the company sees, looking the names up once per page. */
final readonly class EmailInvitationViews
{
    public function __construct(private JobDirectory $jobs, private CompanyMemberAccounts $members) {}

    /**
     * @param  Page<EmailInvitation>  $page
     * @return Page<EmailInvitationView>
     */
    public function forCompany(Page $page): Page
    {
        $invitations = $page->items;
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (EmailInvitation $i): int => $i->jobId, $invitations))));
        $members = $this->members->summaries(array_values(array_unique(array_map(static fn (EmailInvitation $i): int => $i->invitedByUserId, $invitations))));

        return new Page(array_map(static fn (EmailInvitation $i): EmailInvitationView => new EmailInvitationView(
            (int) $i->id, $i->jobId, $titles[$i->jobId] ?? null, $i->name, $i->email, $i->channel, $i->note, $i->status, $i->invitedByUserId,
            $members[$i->invitedByUserId]->name ?? null, $i->hasCv(), $i->resentCount, $i->applicationId, $i->openedAt, $i->respondedAt, $i->expiresAt, $i->createdAt,
        ), $invitations), $page->total, $page->request);
    }
}
