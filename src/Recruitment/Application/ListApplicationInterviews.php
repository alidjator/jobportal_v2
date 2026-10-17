<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Every interview of an application, newest first (the active one, then the ones cancelled, declined or done), for the members who may see its job. */
final readonly class ListApplicationInterviews
{
    public function __construct(private TeamApplicationLookup $lookup, private InterviewRepository $interviews, private InterviewViews $views) {}

    /**
     * @return list<InterviewView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): array
    {
        $this->lookup->find($actor, $applicationId, panelOpens: true);

        return $this->views->of($this->interviews->forApplication($applicationId), withPanel: true);
    }
}
