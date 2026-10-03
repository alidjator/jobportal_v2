<?php

namespace Src\Recruitment\Application\JobBoard\Commands;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\JobActivityNotifier;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ApplyToJob
{
    public function __construct(
        private readonly CandidateJobActivity $activity,
        private readonly JobActivityNotifier $notifier,
    ) {}

    public function __invoke(int $userId, int $jobId, string $resumeId, string $coverLetter): void
    {
        $candidateId = $this->activity->candidateIdForUser($userId);
        $user = $this->activity->userInfo($userId);

        if ($candidateId === null || $user === null) {
            throw new AccessDenied('Only candidates can apply to jobs');
        }

        if (! $this->activity->ownsResume($candidateId, $resumeId)) {
            throw new BusinessRuleViolation('You can not apply on this job. Because this resume is not yours');
        }

        $job = $this->activity->jobSummary($jobId) ?? throw new EntityNotFound('Job not found');

        if ($job['apply_on'] !== 'app') {
            throw new BusinessRuleViolation('You can not apply on this job. Because this job is not for apply on website');
        }

        if ($this->activity->hasApplied($candidateId, $jobId)) {
            throw new BusinessRuleViolation('You have already applied for this job');
        }

        $this->activity->apply($candidateId, $jobId, $resumeId, $coverLetter);

        $this->notifier->applied($user, $job);
    }
}
