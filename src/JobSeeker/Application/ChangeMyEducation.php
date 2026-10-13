<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\HistoryRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A job seeker changes some fields of one of their education entries. The whole entry is checked again. */
final readonly class ChangeMyEducation
{
    public function __construct(
        private JobSeekerGuard $guard,
        private EducationRepository $educations,
        private ReferenceValues $references,
        private JakartaToday $today,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  keyed by property name
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-education` with `errors` per field
     */
    public function execute(Actor $actor, int $id, array $changes): Education
    {
        $userId = $this->guard->userId($actor);
        $current = $this->educations->find($userId, $id) ?? throw EntityNotFound::of('Education', $id);
        $education = $current->with($changes);

        if (isset($changes['educationLevelId']) && ! $this->references->isActive('education-levels', (int) $changes['educationLevelId'])) {
            throw new BusinessRuleViolation('The education level does not exist or is not active.', 'invalid-education', extensions: ['errors' => ['education_level_id' => ['This value does not exist or is not active.']]]);
        }

        HistoryRules::education($education, $this->today->year(), $current);
        $this->educations->save($education, $this->clock->now());

        return $this->educations->find($userId, $id) ?? throw EntityNotFound::of('Education', $id);
    }
}
