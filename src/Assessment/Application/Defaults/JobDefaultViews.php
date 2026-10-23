<?php

namespace Src\Assessment\Application\Defaults;

use Src\Assessment\Domain\JobStageTest;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Recruitment\CompanyStages;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Builds the views of default tests: the names behind the ids and whether the default still works. */
final readonly class JobDefaultViews
{
    public function __construct(
        private CompanyStages $stages,
        private TestRepository $tests,
        private CompanyMemberAccounts $accounts,
        private DefaultTests $rules,
    ) {}

    /**
     * @param  list<JobStageTest>  $defaults
     * @return list<JobDefaultView>
     */
    public function many(int $companyId, array $defaults): array
    {
        if ($defaults === []) {
            return [];
        }

        $stages = $this->stages->ofCompany($companyId);
        $userIds = array_values(array_unique(array_merge(...array_map(static fn (JobStageTest $default): array => $default->assessorIds, $defaults))));
        $names = $userIds === [] ? [] : $this->accounts->summaries($userIds);

        return array_map(function (JobStageTest $default) use ($companyId, $stages, $names): JobDefaultView {
            $test = $this->tests->find($default->testId);

            return new JobDefaultView(
                $default->stageId,
                isset($stages[$default->stageId]) ? $stages[$default->stageId]->name : null,
                $default->testId,
                $test instanceof Test ? $test->title() : null,
                $default->dueDays,
                array_map(static fn (int $userId): array => ['user_id' => $userId, 'name' => isset($names[$userId]) ? $names[$userId]->name : ''], $default->assessorIds),
                $default->advances() ? [
                    'min_percentage' => (int) $default->advanceMinPercentage,
                    'to_stage_id' => (int) $default->advanceToStageId,
                    'to_stage_name' => isset($stages[(int) $default->advanceToStageId]) ? $stages[(int) $default->advanceToStageId]->name : null,
                ] : null,
                $this->problem($companyId, $default),
                $default->updatedAt,
            );
        }, $defaults);
    }

    private function problem(int $companyId, JobStageTest $default): ?string
    {
        try {
            $this->rules->assertValid($companyId, $default);
        } catch (BusinessRuleViolation $violation) {
            return $violation->errorCode();
        }

        return null;
    }
}
