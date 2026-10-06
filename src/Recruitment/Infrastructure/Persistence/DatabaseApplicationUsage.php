<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\ScreeningQuestionUsage;

/**
 * What applications lock: a question is locked when it has answers or is attached to a job that has applications, and a job's questions are locked
 * once the job has any application.
 */
final readonly class DatabaseApplicationUsage implements JobApplicationCheck, ScreeningQuestionUsage
{
    public function __construct(private ConnectionInterface $connection) {}

    public function isLocked(int $questionId): bool
    {
        if ($this->connection->table('recruitment_application_answers')->where('question_id', $questionId)->exists()) {
            return true;
        }

        return $this->connection->table('recruitment_job_questions')
            ->where('question_id', $questionId)
            ->whereIn('job_id', $this->connection->table('recruitment_applications')->select('job_id'))
            ->exists();
    }

    public function hasApplications(int $jobId): bool
    {
        return $this->connection->table('recruitment_applications')->where('job_id', $jobId)->exists();
    }
}
