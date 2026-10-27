<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The presets a member can apply to a job: those of the company, the ones whose level hint is the job's level first and marked suggested. A job without a
 * level, or a preset without a hint, gets no suggestion.
 */
final readonly class ListJobScreeningPresets
{
    public function __construct(private RecruitmentAccess $access, private ScreeningPresetReader $reader) {}

    /**
     * @return Page<JobPresetOption>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when the job does not exist in the company or the member may not see it
     */
    public function execute(Actor $actor, int $jobId, PageRequest $request): Page
    {
        $member = $this->access->viewer($actor, CompanyPermission::JobsCreate);
        $levelId = $this->access->job($member, $jobId)->jobLevelId;
        $page = $this->reader->page(new ScreeningPresetListQuery($member->companyId, null, null, $levelId), $request);

        return new Page(
            array_map(static fn (ScreeningPreset $preset): JobPresetOption => new JobPresetOption($preset, $levelId !== null && $preset->jobLevelId === $levelId), $page->items),
            $page->total,
            $request,
        );
    }
}
