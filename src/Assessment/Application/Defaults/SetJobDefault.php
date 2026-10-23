<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Domain\JobStageTest;
use Src\Assessment\Domain\JobStageTestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Sets the default test of a job for an assessment stage, with the rule that moves an application on by its score, or replaces the one that was there. */
final readonly class SetJobDefault
{
    public function __construct(
        private JobDefaultAccess $access,
        private DefaultTests $rules,
        private JobStageTestRepository $defaults,
        private JobDefaultViews $views,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<int>  $assessorIds
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `stage-not-assessment`, `invalid-assessment-schedule`, `invalid-assessment-test`, `invalid-assessor`, `advance-not-allowed`, `invalid-advance-stage` or `message-link-not-allowed`
     */
    public function execute(Actor $actor, int $jobId, int $stageId, int $testId, int $dueDays, array $assessorIds, ?int $advanceMinPercentage, ?int $advanceToStageId): JobDefaultView
    {
        $companyId = $this->access->companyId($actor, $jobId);
        $default = new JobStageTest($jobId, $stageId, $testId, $dueDays, array_values(array_unique($assessorIds)), $advanceMinPercentage, $advanceToStageId, (int) $actor->id);

        $this->rules->assertValid($companyId, $default);

        $this->transactions->run(fn () => $this->defaults->save($default));

        return $this->views->many($companyId, [$this->defaults->find($jobId, $stageId) ?? $default])[0];
    }
}
