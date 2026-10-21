<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\HistoryRules;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A job seeker adds a job to their history: at most 30 entries, the content checked as a whole, the job type (when given) an active master. */
final readonly class AddMyExperience
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private ExperienceRepository $experiences,
        private ReferenceValues $references,
        private JakartaToday $today,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-experience` with `errors` per field
     * @throws StateConflict `experience-limit-reached`
     */
    public function execute(Actor $actor, WorkExperience $draft): WorkExperience
    {
        $userId = $this->guard->userId($actor);
        if ($draft->jobTypeId !== null && ! $this->references->isActive('job-types', $draft->jobTypeId)) {
            throw new BusinessRuleViolation('The job type does not exist or is not active.', 'invalid-experience', extensions: ['errors' => ['job_type_id' => ['This value does not exist or is not active.']]]);
        }

        HistoryRules::experience($draft, $this->today->month());

        $now = $this->clock->now();

        $id = $this->transactions->run(function () use ($userId, $draft, $now): int {
            // The profile row is locked first, so two simultaneous additions cannot both pass the limit.
            $this->profiles->lock($userId, $now);

            if ($this->experiences->count($userId) >= HistoryRules::EXPERIENCES_MAX) {
                throw new StateConflict('A profile can have at most :max work experiences.', 'experience-limit-reached', parameters: ['max' => HistoryRules::EXPERIENCES_MAX]);
            }

            return $this->experiences->add(new WorkExperience(
                $userId, $draft->jobTitle, $draft->companyName, $draft->jobTypeId, $draft->locationText, $draft->startDate,
                $draft->endDate, $draft->isCurrent, $draft->description,
            ), $now);
        });

        return $this->experiences->find($userId, $id) ?? throw new \LogicException('The new experience was not found.');
    }
}
