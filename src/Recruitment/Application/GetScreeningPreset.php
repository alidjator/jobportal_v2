<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One preset of the caller's company. A preset of another company does not exist. */
final readonly class GetScreeningPreset
{
    public function __construct(private RecruitmentAccess $access, private ScreeningPresetRepository $presets) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): ScreeningPreset
    {
        $member = $this->access->anyOf($actor, CompanyPermission::JobsCreate, CompanyPermission::ScreeningQuestionsManage);
        $preset = $this->presets->find($id);

        if ($preset === null || $preset->companyId !== $member->companyId) {
            throw EntityNotFound::of('Screening preset', $id);
        }

        return $preset;
    }
}
