<?php

namespace Src\Recruitment\Domain;

/** The content of the job as it was when the candidate applied, kept with the application (`recruitment_applications.job_snapshot`); written once, never changed. */
interface ApplicationJobSnapshots
{
    /** @param  array<string, mixed>  $snapshot */
    public function save(int $applicationId, array $snapshot): void;

    /**
     * @return array<string, mixed>|null null when the application has none (it is older than the copy and has not been filled yet) or is not found
     */
    public function find(int $applicationId): ?array;

    /**
     * The applications that have no copy yet and were not erased (an erased application has no profile snapshot either), in id order, after `$afterId`: id and job id.
     *
     * @return list<array{id: int, job_id: int}>
     */
    public function withoutSnapshot(int $afterId, int $limit): array;
}
