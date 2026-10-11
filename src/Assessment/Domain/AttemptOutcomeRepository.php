<?php

namespace Src\Assessment\Domain;

interface AttemptOutcomeRepository
{
    /** Records a signal and returns how many the attempt has now. */
    public function addEvent(int $attemptId, IntegrityEventType $type, \DateTimeImmutable $at): int;

    /**
     * The signals of the attempt, oldest first.
     *
     * @return list<array{type: IntegrityEventType, occurred_at: \DateTimeImmutable}>
     */
    public function eventsOf(int $attemptId): array;

    public function eventCount(int $attemptId): int;

    /** Stores the result, replacing the one of the attempt. */
    public function saveResult(AttemptResult $result): void;

    public function resultOf(int $attemptId): ?AttemptResult;
}
