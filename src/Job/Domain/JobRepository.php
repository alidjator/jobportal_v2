<?php

namespace Src\Job\Domain;

use DateTimeImmutable;

interface JobRepository
{
    /** The job with its skills, tags and benefits, or null when there is none. */
    public function find(int $id): ?Job;

    /**
     * The titles of these jobs, keyed by job id; an id without a job is missing.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function titles(array $ids): array;

    /** Locks the job row until the transaction ends, so two changes to the same job run one after the other. */
    public function lock(int $id): void;

    /** Inserts a new job with its lists and returns it with its id and timestamps. */
    public function create(Job $job): Job;

    /** Stores the content (with its lists) and the status fields of an existing job. */
    public function save(Job $job): void;

    public function delete(int $id): void;

    /**
     * The ids of live jobs whose deadline is before `$today` (a date; the deadline day itself is still open), oldest deadline first, at most `$limit`.
     *
     * @return list<int>
     */
    public function idsDueForExpiry(DateTimeImmutable $today, int $limit): array;
}
