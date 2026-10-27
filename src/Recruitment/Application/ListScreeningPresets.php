<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The presets of the caller's company, for every member who can pick questions. */
final readonly class ListScreeningPresets
{
    public function __construct(private RecruitmentAccess $access, private ScreeningPresetReader $reader) {}

    /**
     * @return Page<ScreeningPreset>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor, ?string $search, ?int $jobLevelId, PageRequest $request): Page
    {
        $member = $this->access->anyOf($actor, CompanyPermission::JobsCreate, CompanyPermission::ScreeningQuestionsManage);

        return $this->reader->page(new ScreeningPresetListQuery($member->companyId, $search, $jobLevelId), $request);
    }
}
