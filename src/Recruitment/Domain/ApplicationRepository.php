<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

interface ApplicationRepository
{
    /** Whether the applicant has a live application (not withdrawn) for the job: applying again is not allowed. */
    public function hasLiveApplication(int $userId, int $jobId): bool;

    /** How many applications the applicant submitted since this moment, withdrawn ones included. */
    public function countSubmittedSince(int $userId, DateTimeImmutable $since): int;

    /**
     * The application with that id, whoever it belongs to (the caller checks that the company's team may see it). With `$forUpdate` the row is locked until
     * the transaction ends.
     */
    public function find(int $id, bool $forUpdate = false): ?Application;

    /**
     * The applicant's own application, or null when there is none with that id (another person's application does not exist for them).
     * With `$forUpdate` the row is locked until the transaction ends, so two changes to it run one after the other.
     */
    public function findOfApplicant(int $userId, int $id, bool $forUpdate = false): ?Application;

    /** Gives (a moment) or revokes (null) the applicant's consent to the company's talent pool; `updated_at` stays, the consent is not a change of the application. */
    public function changeTalentPoolConsent(int $id, ?DateTimeImmutable $at): void;

    /**
     * Records that a recruiter opened the application, once: a single conditional update that keeps the first time and leaves `updated_at` alone, so two
     * recruiters opening it together need no lock.
     */
    public function markViewed(int $id, DateTimeImmutable $at): void;

    /** Stores the withdrawal of an application: the new stage, the freed key, the note, the reason, and the history entry (from the stage it was in, by the applicant). */
    public function recordWithdrawal(Application $before, Application $after, DateTimeImmutable $at, ?WithdrawalReason $reason = null): void;

    /**
     * Stores a withdrawal marked by a team member: as `recordWithdrawal`, with the member as the actor and the team's own note on the history entry (the
     * application's `withdrawn_note` stays the applicant's).
     */
    public function recordWithdrawalByTeam(Application $before, Application $after, int $actorId, WithdrawalReason $reason, ?string $note, DateTimeImmutable $at): void;

    /**
     * Stores a move by a recruiter: the new stage (the key stays, a rejected application still blocks applying again) and a history entry with the actor, the
     * rejection reason and the note. A move into `hired` stores the hire details; a move out of `hired` clears them.
     */
    public function recordMove(Application $before, Application $after, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at, ?HireDetails $hire = null): void;

    /** Stores the acceptance of a job offer: the application moves to `hired` with its hire details, and a history entry by the applicant. */
    public function recordHire(Application $before, Application $after, DateTimeImmutable $at, ?HireDetails $hire = null): void;

    /** The hire details of an application, or null when it has none (not hired, or hired before they existed). */
    public function hireOf(int $id): ?HireDetails;

    /** Replaces the hire details of a hired application. */
    public function updateHire(int $id, HireDetails $hire, DateTimeImmutable $at): void;

    /**
     * Moves every application in one stage to another stage of the same type (used when an extra stage is deleted), recording one history entry
     * per application with the system as the actor.
     *
     * @return int how many applications moved
     */
    public function moveStage(int $fromStageId, int $toStageId, StageType $type, DateTimeImmutable $at): int;

    /**
     * Stores a new application with its answers and its first stage history entry (nothing to applied, by the applicant).
     *
     * @param  list<ApplicationAnswer>  $answers
     * @return Application the stored application, with its id
     *
     * @throws StateConflict `already-applied` when the applicant already has a live application for the job
     */
    public function add(Application $application, array $answers): Application;
}
