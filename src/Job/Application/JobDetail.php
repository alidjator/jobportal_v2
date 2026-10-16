<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;

/**
 * A job as its company sees it: the job itself, the skills and benefits with their names (in the request language), and who is assigned. The tags are
 * part of the job content as text.
 */
final readonly class JobDetail
{
    /**
     * @param  list<array{id: int, name: string}>  $skills
     * @param  list<array{id: int, name: string}>  $benefits
     * @param  list<array{user_id: int, name: string, role: string, job_role: string}>  $assignees
     */
    public function __construct(
        public Job $job,
        public array $skills,
        public array $benefits,
        public array $assignees,
    ) {}
}
