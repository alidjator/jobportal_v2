<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface VideoTakeRepository
{
    public function find(int $id, bool $forUpdate = false): ?VideoTake;

    public function add(VideoTake $take): VideoTake;

    public function update(VideoTake $take): void;

    /** Sets the day the files of the attempt's complete takes are purged. */
    public function scheduleExpiry(int $attemptId, DateTimeImmutable $expiresAt): void;

    /** @return list<VideoTake> complete takes whose retention period is over, at most `$limit` */
    public function expired(DateTimeImmutable $now, int $limit): array;

    /** @return list<VideoTake> takes still `recording` that were started before the given time, at most `$limit` */
    public function stale(DateTimeImmutable $startedBefore, int $limit): array;

    /** @return list<VideoTake> every take of the question, oldest first (rejected ones included: they count as retakes used) */
    public function ofQuestion(int $attemptId, int $questionId): array;
}
