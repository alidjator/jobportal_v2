<?php

namespace Src\Recruitment\Application\JobBoard\Queries;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\Page;

final class ListJobAlerts
{
    public function __construct(private readonly CandidateJobActivity $activity) {}

    public function __invoke(int $userId, int $perPage, int $page): Page
    {
        return $this->activity->jobAlerts($userId, $perPage, $page);
    }
}
