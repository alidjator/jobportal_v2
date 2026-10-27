<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\ScreeningPreset;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin replaces the name, the level hint and the whole ordered list of a preset. Jobs that used it are not touched. */
final readonly class UpdateScreeningPreset
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
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-screening-preset`
     * @throws StateConflict `screening-limit-reached`
     */
    public function execute(Actor $actor, int $id, string $name, ?int $jobLevelId, array $items): ScreeningPreset
    {
        $member = $this->access->permitted($actor, CompanyPermission::ScreeningQuestionsManage);
        $this->switch->assertOn($member->companyId);

        $this->transactions->run(function () use ($member, $id, $name, $jobLevelId, $items): void {
            $preset = $this->presets->find($id);

            if ($preset === null || $preset->companyId !== $member->companyId) {
                throw EntityNotFound::of('Screening preset', $id);
            }

            $currentIds = array_map(static fn (JobQuestion $current): int => $current->question->id, $preset->questions);
            $checked = $this->check->run($member->companyId, $name, $jobLevelId, $items, $currentIds, $id);

            $this->presets->update($id, $checked['name'], $jobLevelId, $checked['drafts'], $this->clock->now());
        });

        return $this->presets->find($id) ?? throw EntityNotFound::of('Screening preset', $id);
    }
}
