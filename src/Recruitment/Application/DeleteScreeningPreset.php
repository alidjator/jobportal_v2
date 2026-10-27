<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin deletes a preset. Nothing points back to it, so jobs that used it keep their questions. */
final readonly class DeleteScreeningPreset
{
    public function __construct(private RecruitmentAccess $access, private ScreeningSwitch $switch, private ScreeningPresetRepository $presets) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `screening-disabled`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): void
    {
        $member = $this->access->permitted($actor, CompanyPermission::ScreeningQuestionsManage);
        $this->switch->assertOn($member->companyId);
        $preset = $this->presets->find($id);

        if ($preset === null || $preset->companyId !== $member->companyId) {
            throw EntityNotFound::of('Screening preset', $id);
        }

        $this->presets->delete($id);
    }
}
