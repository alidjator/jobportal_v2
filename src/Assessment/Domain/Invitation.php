<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A test sent to a candidate for one application (`stageId` is the assessment stage it was sent in, null for one sent by hand outside a move). It is invited until the candidate starts (or declines it), then started until the attempt is submitted; the team
 * can cancel an active one and an unstarted one expires at its due time. Cancelled, expired, declined and submitted invitations are kept as history. The test never
 * changes after the invitation is sent: a published test that has invitations is locked.
 */
final readonly class Invitation
{
    /** @param  list<int>  $assessorIds  the company members who score the long-text answers */
    public function __construct(
        public ?int $id,
        public int $applicationId,
        public int $companyId,
        public int $jobId,
        public int $applicantUserId,
        public int $testId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $dueAt,
        public InvitationStatus $status,
        public int $invitedByUserId,
        public array $assessorIds,
        public ?DateTimeImmutable $remindedAt = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public InvitationMode $mode = InvitationMode::Online,
        public ?string $location = null,
        public ?InvitationAttachment $attachment = null,
        public ?string $note = null,
        public ?int $stageId = null,
    ) {}

    /** @param  list<int>  $assessorIds */
    public static function send(
        int $applicationId,
        int $companyId,
        int $jobId,
        int $applicantUserId,
        int $testId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $dueAt,
        int $invitedByUserId,
        array $assessorIds,
        InvitationMode $mode = InvitationMode::Online,
        ?string $location = null,
        ?string $note = null,
        ?int $stageId = null,
    ): self {
        return new self(
            null, $applicationId, $companyId, $jobId, $applicantUserId, $testId, $startsAt, $dueAt, InvitationStatus::Invited, $invitedByUserId, $assessorIds,
            mode: $mode, location: $mode === InvitationMode::Onsite ? $location : null, note: $note, stageId: $stageId,
        );
    }

    /** The candidate may begin: the invitation is still unused, its start time has come and its due time has not passed. */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return $this->status === InvitationStatus::Invited && $this->startsAt <= $now && $this->dueAt > $now;
    }

    /**
     * The candidate begins the test. A test that was started stays open until its attempt ends, even past the due time.
     *
     * @throws StateConflict `invitation-not-open`
     */
    public function start(DateTimeImmutable $now): self
    {
        if (! $this->isOpenAt($now)) {
            throw new StateConflict('This test cannot be started now: it is cancelled, expired, already started, or not open yet.', 'invitation-not-open');
        }

        return $this->with(InvitationStatus::Started, $this->remindedAt, $this->closedAt);
    }

    /**
     * @throws StateConflict `invitation-not-started`
     */
    public function submit(DateTimeImmutable $at): self
    {
        if ($this->status !== InvitationStatus::Started) {
            throw new StateConflict('Only a test that was started can be submitted.', 'invitation-not-started');
        }

        return $this->with(InvitationStatus::Submitted, $this->remindedAt, $at);
    }

    /**
     * @throws StateConflict `invitation-not-active`
     */
    public function cancel(DateTimeImmutable $now): self
    {
        if (! $this->status->isActive()) {
            throw new StateConflict('Only an active test invitation can be cancelled.', 'invitation-not-active');
        }

        return $this->with(InvitationStatus::Cancelled, $this->remindedAt, $now);
    }

    /**
     * The candidate turns the test down before starting it, whether or not its start time has come.
     *
     * @throws StateConflict `invitation-not-open`
     */
    public function decline(DateTimeImmutable $now): self
    {
        if ($this->status !== InvitationStatus::Invited || $this->dueAt <= $now) {
            throw new StateConflict('This test cannot be declined now: it is already started, cancelled, expired or declined.', 'invitation-not-open');
        }

        return $this->with(InvitationStatus::Declined, $this->remindedAt, $now);
    }

    /**
     * @throws StateConflict `invitation-not-invited`
     */
    public function expire(DateTimeImmutable $now): self
    {
        if ($this->status !== InvitationStatus::Invited) {
            throw new StateConflict('Only a test that was not started can expire.', 'invitation-not-invited');
        }

        return $this->with(InvitationStatus::Expired, $this->remindedAt, $now);
    }

    /**
     * Puts the file on the invitation, or takes it off (null). Only while the candidate has not started: after that the invitation is history.
     *
     * @throws StateConflict `invitation-not-active`
     */
    public function withAttachment(?InvitationAttachment $attachment): self
    {
        if ($this->status !== InvitationStatus::Invited) {
            throw new StateConflict('The file of a test invitation can change only while the test has not been started.', 'invitation-not-active');
        }

        return new self(
            $this->id, $this->applicationId, $this->companyId, $this->jobId, $this->applicantUserId, $this->testId, $this->startsAt, $this->dueAt,
            $this->status, $this->invitedByUserId, $this->assessorIds, $this->remindedAt, $this->closedAt, $this->createdAt, $this->mode, $this->location, $attachment, $this->note, $this->stageId,
        );
    }

    public function reminded(DateTimeImmutable $now): self
    {
        return $this->with($this->status, $now, $this->closedAt);
    }

    private function with(InvitationStatus $status, ?DateTimeImmutable $remindedAt, ?DateTimeImmutable $closedAt): self
    {
        return new self(
            $this->id, $this->applicationId, $this->companyId, $this->jobId, $this->applicantUserId, $this->testId, $this->startsAt, $this->dueAt,
            $status, $this->invitedByUserId, $this->assessorIds, $remindedAt, $closedAt, $this->createdAt, $this->mode, $this->location, $this->attachment, $this->note, $this->stageId,
        );
    }
}
