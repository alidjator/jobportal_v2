<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The interviews of a member (`docs/design/phase-8-interviewers.md` section 1): the ones they sit on, whatever the job. A member with `interviews.manage` can ask for
 * every interview of the jobs they may see instead. Cancelled and declined interviews are not listed.
 */
final readonly class ListMyInterviews
{
    public function __construct(
        private RecruitmentAccess $access,
        private HiringManagerScope $scope,
        private MyInterviewReader $reader,
        private JobDirectory $jobs,
        private Clock $clock,
    ) {}

    /**
     * @return CursorPage<MyInterviewRow>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required` (all interviews without `interviews.manage` and `applications.view`)
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function execute(Actor $actor, bool $upcoming, bool $scorecardPending, ?string $rsvp, bool $allOfCompany, CursorRequest $request): CursorPage
    {
        $member = $allOfCompany ? $this->access->viewer($actor, CompanyPermission::InterviewsManage) : $this->access->member($actor);
        $jobIds = $allOfCompany && ! $member->viewsAll() ? $this->jobs->assignedJobIds($member->companyId, $member->userId) : null;
        $query = new MyInterviewQuery(
            $member->userId, $member->companyId, $upcoming, $scorecardPending, $rsvp, $allOfCompany, $jobIds, $allOfCompany ? $this->scope->boundJobIds($member) : [], $this->clock->now(),
        );
        $page = $this->reader->page($query, $request);
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (MyInterviewRow $row): int => $row->jobId, $page->items))));

        return new CursorPage(
            array_map(static fn (MyInterviewRow $row): MyInterviewRow => $row->withJobTitle($titles[$row->jobId] ?? null), $page->items),
            $page->nextCursor,
            $page->perPage,
        );
    }
}
