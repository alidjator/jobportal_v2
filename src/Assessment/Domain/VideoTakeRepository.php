<?php

namespace Src\Assessment\Domain;

interface VideoTakeRepository
{
    public function find(int $id, bool $forUpdate = false): ?VideoTake;

    public function add(VideoTake $take): VideoTake;

    public function update(VideoTake $take): void;

    /** @return list<VideoTake> every take of the question, oldest first (rejected ones included: they count as retakes used) */
    public function ofQuestion(int $attemptId, int $questionId): array;
}
