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

    /** Stores the withdrawal of an application: the new stage, the freed key, the note, and the history entry (from the stage it was in, by the applicant). */
    public function recordWithdrawal(Application $before, Application $after, DateTimeImmutable $at): void;

    /**
     * Stores a move by a recruiter: the new stage (the key stays, a rejected application still blocks applying again) and a history entry with the actor, the
     * rejection reason and the note.
     */
    public function recordMove(Application $before, Application $after, ?int $actorId, ?RejectionReason $reason, ?string $note, DateTimeImmutable $at): void;

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
