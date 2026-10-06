<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\ExperienceRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class DeleteMyExperience
{
    public function __construct(private JobSeekerGuard $guard, private ExperienceRepository $experiences) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): void
    {
        $userId = $this->guard->userId($actor);

        if ($this->experiences->find($userId, $id) === null) {
            throw EntityNotFound::of('Work experience', $id);
        }

        $this->experiences->delete($userId, $id);
    }
}
