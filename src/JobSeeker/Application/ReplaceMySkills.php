<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\ProfileListRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A job seeker sets their whole list of skills: at most 30, no repeats, every one an active skill, in the order given. */
final readonly class ReplaceMySkills
{
    public const int MAX = 30;

    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileListRepository $lists,
        private ReferenceValues $references,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<int>  $skillIds
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-skills` with `errors` per `skill_ids.<n>` or `skill_ids`
     */
    public function execute(Actor $actor, array $skillIds): void
    {
        $userId = $this->guard->userId($actor);
        $errors = [];

        if (count($skillIds) > self::MAX && count($skillIds) > count($this->lists->skillIds($userId))) {
            $errors['skill_ids'][] = 'A profile can have at most '.self::MAX.' skills.';
        }

        if (count($skillIds) !== count(array_unique($skillIds))) {
            $errors['skill_ids'][] = 'A skill can be listed only once.';
        }

        foreach ($skillIds as $index => $skillId) {
            if (! $this->references->isActive('skills', $skillId)) {
                $errors["skill_ids.{$index}"][] = 'This skill does not exist or is not active.';
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The skills are not valid.', 'invalid-skills', extensions: ['errors' => $errors]);
        }

        $this->transactions->run(fn () => $this->lists->replaceSkills($userId, $skillIds));
    }
}
