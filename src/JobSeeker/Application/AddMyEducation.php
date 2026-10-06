<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\HistoryRules;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A job seeker adds an education entry: at most 15, the level an active master, the content checked as a whole. */
final readonly class AddMyEducation
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private EducationRepository $educations,
        private ReferenceValues $references,
        private JakartaToday $today,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-education` with `errors` per field
     * @throws StateConflict `education-limit-reached`
     */
    public function execute(Actor $actor, Education $draft): Education
    {
        $userId = $this->guard->userId($actor);

        if (! $this->references->isActive('education-levels', $draft->educationLevelId)) {
            throw new BusinessRuleViolation('The education level does not exist or is not active.', 'invalid-education', extensions: ['errors' => ['education_level_id' => ['This value does not exist or is not active.']]]);
        }

        HistoryRules::education($draft, $this->today->year());

        $now = $this->clock->now();

        $id = $this->transactions->run(function () use ($userId, $draft, $now): int {
            // The profile row is locked first, so two simultaneous additions cannot both pass the limit.
            $this->profiles->lock($userId, $now);

            if ($this->educations->count($userId) >= HistoryRules::EDUCATIONS_MAX) {
                throw new StateConflict('A profile can have at most '.HistoryRules::EDUCATIONS_MAX.' education entries.', 'education-limit-reached');
            }

            return $this->educations->add(new Education(
                $userId, $draft->educationLevelId, $draft->institution, $draft->fieldOfStudy, $draft->startYear, $draft->endYear, $draft->isCurrent, $draft->gpa, $draft->description,
            ), $now);
        });

        return $this->educations->find($userId, $id) ?? throw new \LogicException('The new education entry was not found.');
    }
}
