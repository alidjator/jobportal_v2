<?php

namespace Src\Assessment\Domain;

interface JobStageTestRepository
{
    public function find(int $jobId, int $stageId): ?JobStageTest;

    /** @return list<JobStageTest> the defaults of the job, by stage id */
    public function ofJob(int $jobId): array;

    /** Creates the default of the job for the stage or replaces it, with its assessors. */
    public function save(JobStageTest $default): void;

    public function delete(int $jobId, int $stageId): void;

    /** Copies every default of one job to another (a cloned job). */
    public function copy(int $fromJobId, int $toJobId, int $updatedBy): void;
}
