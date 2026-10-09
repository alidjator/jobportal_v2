<?php

namespace Src\JobInvitation\Application;

use DateTimeImmutable;
use Src\JobInvitation\Domain\InvitationStatus;

/**
 * An invitation as a list or detail shows it. `expired` is true while the invitation is still open (`sent` or `viewed`) but its job is not live. The company
 * side fills the candidate and the member who invited; the candidate side fills the company and the job title.
 */
final readonly class InvitationView
{
    public function __construct(
        public int $id,
        public int $jobId,
        public ?string $jobTitle,
        public int $companyId,
        public ?string $companyName,
        public ?string $candidateSlug,
        public ?string $candidateName,
        public ?int $invitedByUserId,
        public ?string $invitedByName,
        public ?string $note,
        public InvitationStatus $status,
        public bool $expired,
        public ?string $declineReason,
        public ?DateTimeImmutable $viewedAt,
        public ?DateTimeImmutable $respondedAt,
        public ?int $applicationId,
        public DateTimeImmutable $createdAt,
    ) {}
}
