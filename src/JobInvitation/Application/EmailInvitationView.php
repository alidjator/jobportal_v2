<?php

namespace Src\JobInvitation\Application;

use DateTimeImmutable;
use Src\JobInvitation\Domain\EmailInvitationChannel;
use Src\JobInvitation\Domain\EmailInvitationStatus;

/** An invitation by email as the company sees it. The name and the email are null once the invitation ended without a claim. */
final readonly class EmailInvitationView
{
    public function __construct(
        public int $id,
        public int $jobId,
        public ?string $jobTitle,
        public ?string $name,
        public ?string $email,
        public EmailInvitationChannel $channel,
        public ?string $note,
        public EmailInvitationStatus $status,
        public int $invitedByUserId,
        public ?string $invitedByName,
        public bool $hasCv,
        public int $resentCount,
        public ?int $applicationId,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $respondedAt,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
