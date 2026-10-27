<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin saves a named set of library questions with their flags, to apply to jobs later. It follows the rules of a job's list (`ScreeningItemDrafter`). */
final readonly class CreateScreeningPreset
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningPresetCheck $check,
        private ScreeningPresetRepository $presets,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}>  $items
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `screening-disabled`
     * @throws BusinessRuleViolation `invalid-screening-preset`
     * @throws StateConflict `screening-limit-reached`
     */
    public function execute(Actor $actor, string $name, ?int $jobLevelId, array $items): ScreeningPreset
    {
        $member = $this->access->permitted($actor, CompanyPermission::ScreeningQuestionsManage);
        $this->switch->assertOn($member->companyId);

        $id = $this->transactions->run(function () use ($member, $name, $jobLevelId, $items): int {
            $checked = $this->check->run($member->companyId, $name, $jobLevelId, $items, [], null);

            return $this->presets->create($member->companyId, $checked['name'], $jobLevelId, $member->userId, $checked['drafts'], $this->clock->now());
        });

        return $this->presets->find($id) ?? throw new \LogicException('The new preset was not found.');
    }
}
