<?php

namespace Src\JobInvitation\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An invitation of a person without an account (or not yet applying) to a job, entered by a member of the company: a walk-in, a referral, a CV received by email
 * (`docs/design/phase-8-manual-candidates.md`). Immutable: a change returns a new instance. Only the hash of the link's token is kept. The name, the email and the CV
 * are removed when the invitation ends without a claim.
 */
final readonly class EmailInvitation
{
    public const int VALID_DAYS = 30;

    public const int MAX_RESENDS = 3;

    public const int NAME_MAX_LENGTH = 150;

    public function __construct(
        public int $companyId,
        public int $jobId,
        public ?string $name,
        public ?string $email,
        public EmailInvitationChannel $channel,
        public ?string $note,
        public string $tokenHash,
        public ?string $cvPath,
        public ?string $cvName,
        public EmailInvitationStatus $status,
        public int $invitedByUserId,
        public ?int $claimedByUserId,
        public ?int $applicationId,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $respondedAt,
        public DateTimeImmutable $expiresAt,
        public int $resentCount,
        public DateTimeImmutable $lastSentAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `note-too-long` */
    public static function send(
        int $companyId, int $jobId, string $name, string $email, EmailInvitationChannel $channel, ?string $note, string $tokenHash, ?string $cvPath, ?string $cvName,
        int $invitedByUserId, DateTimeImmutable $now,
    ): self {
        return new self(
            $companyId, $jobId, trim($name), mb_strtolower(trim($email)), $channel, Invitation::checkedNote($note), $tokenHash, $cvPath, $cvName, EmailInvitationStatus::Sent,
            $invitedByUserId, null, null, null, null, $now->modify('+'.self::VALID_DAYS.' days'), 0, $now, $now, $now,
        );
    }

    /** The link was opened for the first time; later openings change nothing. */
    public function opened(DateTimeImmutable $now): self
    {
        return $this->status === EmailInvitationStatus::Sent ? $this->with(['status' => EmailInvitationStatus::Opened, 'openedAt' => $now, 'updatedAt' => $now]) : $this;
    }

    /** Whether the link still works: the invitation is open and its 30 days are not over. */
    public function claimable(DateTimeImmutable $now): bool
    {
        return $this->status->isOpen() && $this->expiresAt > $now;
    }

    /** @throws StateConflict `invitation-closed` */
    public function claimedBy(int $userId, DateTimeImmutable $now): self
    {
        if (! $this->claimable($now)) {
            throw new StateConflict('This invitation is no longer open.', 'invitation-closed');
        }

        return $this->with(['status' => EmailInvitationStatus::Claimed, 'claimedByUserId' => $userId, 'openedAt' => $this->openedAt ?? $now, 'respondedAt' => $now, 'updatedAt' => $now]);
    }

    /**
     * The person refuses. The name, the email and the CV go at once.
     *
     * @throws StateConflict `invitation-closed`
     */
    public function declined(DateTimeImmutable $now): self
    {
        if (! $this->status->isOpen()) {
            throw new StateConflict('This invitation is no longer open.', 'invitation-closed');
        }

        return $this->erased(EmailInvitationStatus::Declined, $now);
    }

    /**
     * The team withdraws it. The name, the email and the CV go at once.
     *
     * @throws StateConflict `invitation-closed`
     */
    public function cancelled(DateTimeImmutable $now): self
    {
        if (! $this->status->isOpen()) {
            throw new StateConflict('This invitation is no longer open.', 'invitation-closed');
        }

        return $this->erased(EmailInvitationStatus::Cancelled, $now);
    }

    /** An open invitation nobody answered in 30 days: it keeps its row for the counts and loses the personal data. */
    public function expired(DateTimeImmutable $now): self
    {
        return $this->erased(EmailInvitationStatus::Expired, $now);
    }

    /**
     * Sends the link again, with a new token: once a day, at most three times.
     *
     * @throws StateConflict `invitation-closed`
     * @throws BusinessRuleViolation `resend-limit`
     */
    public function resent(string $tokenHash, DateTimeImmutable $now): self
    {
        if (! $this->claimable($now)) {
            throw new StateConflict('This invitation is no longer open.', 'invitation-closed');
        }

        if ($this->resentCount >= self::MAX_RESENDS || $this->lastSentAt > $now->modify('-1 day')) {
            throw new BusinessRuleViolation('The invitation can be sent again once a day, at most :max times.', 'resend-limit', parameters: ['max' => self::MAX_RESENDS]);
        }

        return $this->with(['tokenHash' => $tokenHash, 'resentCount' => $this->resentCount + 1, 'lastSentAt' => $now, 'updatedAt' => $now]);
    }

    /** The team's copy of the CV is dropped: the person chose another resume, or took this one into their own. */
    public function withoutCv(DateTimeImmutable $now): self
    {
        return $this->with(['cvPath' => null, 'cvName' => null, 'updatedAt' => $now]);
    }

    /** The claiming person applied. */
    public function applied(int $applicationId, DateTimeImmutable $now): self
    {
        return $this->with(['applicationId' => $applicationId, 'updatedAt' => $now]);
    }

    public function hasCv(): bool
    {
        return $this->cvPath !== null;
    }

    private function erased(EmailInvitationStatus $status, DateTimeImmutable $now): self
    {
        return $this->with(['status' => $status, 'name' => null, 'email' => null, 'cvPath' => null, 'cvName' => null, 'respondedAt' => $now, 'updatedAt' => $now]);
    }

    /** @param  array<string, mixed>  $changes */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
