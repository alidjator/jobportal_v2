<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\EducationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class DeleteMyEducation
{
    public function __construct(private JobSeekerGuard $guard, private EducationRepository $educations) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): void
    {
        $userId = $this->guard->userId($actor);

        if ($this->educations->find($userId, $id) === null) {
            throw EntityNotFound::of('Education', $id);
        }

        $this->educations->delete($userId, $id);
    }
}
