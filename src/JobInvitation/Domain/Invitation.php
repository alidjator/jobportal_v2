<?php

namespace Src\JobInvitation\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An invitation of a candidate to apply to one job of a company. Immutable: a change returns a new instance. There is one per candidate and job. The
 * candidate and the member are accounts of other contexts, so they are plain ids.
 */
final readonly class Invitation
{
    public const int MAX_NOTE_LENGTH = 1000;

    public const int MAX_REASON_LENGTH = 500;

    public function __construct(
        public int $companyId,
        public int $jobId,
        public int $userId,
        public int $invitedByUserId,
        public ?string $note,
        public InvitationStatus $status,
        public ?string $declineReason,
        public ?DateTimeImmutable $viewedAt,
        public ?DateTimeImmutable $respondedAt,
        public ?int $applicationId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `note-too-long` */
    public static function send(int $companyId, int $jobId, int $userId, int $invitedByUserId, ?string $note, DateTimeImmutable $now): self
    {
        $note = self::clean($note);

        if ($note !== null && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new BusinessRuleViolation('The note may have at most '.self::MAX_NOTE_LENGTH.' characters.', 'note-too-long');
        }

        return new self($companyId, $jobId, $userId, $invitedByUserId, $note, InvitationStatus::Sent, null, null, null, null, $now, $now);
    }

    /** The first opening by the candidate; later ones change nothing. */
    public function viewed(DateTimeImmutable $now): self
    {
        return $this->status === InvitationStatus::Sent ? $this->with(['status' => InvitationStatus::Viewed, 'viewedAt' => $now, 'updatedAt' => $now]) : $this;
    }

    /**
     * @throws StateConflict `invitation-closed`
     * @throws BusinessRuleViolation `reason-too-long`
     */
    public function decline(?string $reason, DateTimeImmutable $now): self
    {
        if (! $this->status->isOpen()) {
            throw new StateConflict('This invitation was already answered.', 'invitation-closed');
        }

        $reason = self::clean($reason);

        if ($reason !== null && mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new BusinessRuleViolation('The reason may have at most '.self::MAX_REASON_LENGTH.' characters.', 'reason-too-long');
        }

        return $this->with(['status' => InvitationStatus::Declined, 'declineReason' => $reason, 'viewedAt' => $this->viewedAt ?? $now, 'respondedAt' => $now, 'updatedAt' => $now]);
    }

    /** An application on the job, whatever the invitation's state: the later fact wins, so a declined one becomes applied. Telling it twice changes nothing. */
    public function applied(int $applicationId, DateTimeImmutable $now): self
    {
        if ($this->status === InvitationStatus::Applied) {
            return $this;
        }

        return $this->with(['status' => InvitationStatus::Applied, 'applicationId' => $applicationId, 'viewedAt' => $this->viewedAt ?? $now, 'respondedAt' => $now, 'updatedAt' => $now]);
    }

    /** @param  array<string, mixed>  $changes */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    private static function clean(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === '' ? null : $text;
    }
}
