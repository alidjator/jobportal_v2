<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface JobQuestionRepository
{
    /**
     * The questions of a job in their order.
     *
     * @return list<JobQuestion>
     */
    public function forJob(int $jobId): array;

    /**
     * Makes the questions of the job the given list: a question already attached keeps its row and takes the new flags, a new one is attached, one that is
     * not listed is detached.
     *
     * @param  list<JobQuestionDraft>  $drafts  in their order
     */
    public function replaceForJob(int $jobId, array $drafts, DateTimeImmutable $at): void;
}
