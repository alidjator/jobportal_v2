<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** The kit a job preselects for each of its company's interview stages. */
interface JobKitDefaults
{
    /**
     * @return array<int, int> the kit id by stage id
     */
    public function forJob(int $jobId): array;

    public function kitFor(int $jobId, int $stageId): ?int;

    public function set(int $jobId, int $stageId, int $kitId, DateTimeImmutable $at): void;

    public function remove(int $jobId, int $stageId): void;
}
