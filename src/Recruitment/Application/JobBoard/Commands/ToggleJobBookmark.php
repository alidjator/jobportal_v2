<?php

namespace Src\Recruitment\Application\JobBoard\Commands;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\JobActivityNotifier;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ToggleJobBookmark
{
    public function __construct(
        private readonly CandidateJobActivity $activity,
        private readonly JobActivityNotifier $notifier,
    ) {}

    /** @return bool true bila bookmark ditambahkan, false bila dihapus */
    public function __invoke(int $userId, int $jobId): bool
    {
        $candidateId = $this->activity->candidateIdForUser($userId);
        $user = $this->activity->userInfo($userId);

        if ($candidateId === null || $user === null) {
            throw new AccessDenied('Only candidates can bookmark jobs');
        }

        $job = $this->activity->jobSummary($jobId) ?? throw new EntityNotFound('Job not found');

        $attached = $this->activity->toggleBookmark($candidateId, $jobId);

        if ($attached) {
            $this->notifier->bookmarked($user, $job);
        }

        return $attached;
    }
}
