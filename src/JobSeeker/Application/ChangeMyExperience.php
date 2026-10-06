<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\HistoryRules;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A job seeker changes some fields of one of their experiences. The whole entry is checked again, so a partial change cannot leave it inconsistent. */
final readonly class ChangeMyExperience
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ExperienceRepository $experiences,
        private ReferenceValues $references,
        private JakartaToday $today,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  keyed by property name
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-experience` with `errors` per field
     */
    public function execute(Actor $actor, int $id, array $changes): WorkExperience
    {
        $userId = $this->guard->userId($actor);
        $current = $this->experiences->find($userId, $id) ?? throw EntityNotFound::of('Work experience', $id);
        $experience = $current->with($changes);

        if (($changes['jobTypeId'] ?? null) !== null && ! $this->references->isActive('job-types', (int) $changes['jobTypeId'])) {
            throw new BusinessRuleViolation('The job type does not exist or is not active.', 'invalid-experience', extensions: ['errors' => ['job_type_id' => ['This value does not exist or is not active.']]]);
        }

        HistoryRules::experience($experience, $this->today->month());
        $this->experiences->save($experience, $this->clock->now());

        return $this->experiences->find($userId, $id) ?? throw EntityNotFound::of('Work experience', $id);
    }
}
