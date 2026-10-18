<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\JobInvitation\LatestInvitation;
use Src\Shared\Application\JobInvitation\LatestInvitations;

/** The latest invitation a company sent to each of some job seekers, with the title of its job (used by the talent pool list). */
final readonly class LatestInvitationsOfCompany implements LatestInvitations
{
    public function __construct(private InvitationRepository $invitations, private JobDirectory $jobs) {}

    public function ofCompany(int $companyId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $latest = $this->invitations->latestOfCompany($companyId, $userIds);
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->jobId, $latest))));

        return array_map(static fn (Invitation $invitation): LatestInvitation => new LatestInvitation($titles[$invitation->jobId] ?? null, $invitation->status->value, $invitation->createdAt), $latest);
    }
}
