<?php

namespace Src\JobInvitation\Application\Export;

use DateTimeInterface;
use Src\JobInvitation\Domain\Invitation;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The JobInvitation part of the personal data export: the invitations to apply that companies sent, with the job seeker's own answer. */
final readonly class InvitationsDataExporter implements PersonalDataExporter
{
    public function __construct(private InvitationRepository $invitations, private JobDirectory $jobs, private CompanyDirectory $companies) {}

    public function key(): string
    {
        return 'job_invitations';
    }

    public function export(int $userId): PersonalDataSection
    {
        $invitations = [];

        for ($page = 1; ; $page++) {
            $result = $this->invitations->ofUser($userId, null, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            array_push($invitations, ...$result->items);

            if (count($result->items) < PageRequest::MAX_PER_PAGE) {
                break;
            }
        }

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->jobId, $invitations))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (Invitation $invitation): int => $invitation->companyId, $invitations))));

        return new PersonalDataSection(array_map(static fn (Invitation $invitation): array => [
            'company' => ($companies[$invitation->companyId] ?? null)?->name,
            'job' => $titles[$invitation->jobId] ?? null,
            'note' => $invitation->note,
            'status' => $invitation->status->value,
            'decline_reason' => $invitation->declineReason,
            'invited_at' => $invitation->createdAt->format(DateTimeInterface::ATOM),
            'viewed_at' => $invitation->viewedAt?->format(DateTimeInterface::ATOM),
            'responded_at' => $invitation->respondedAt?->format(DateTimeInterface::ATOM),
            'applied' => $invitation->applicationId !== null,
        ], $invitations));
    }
}
