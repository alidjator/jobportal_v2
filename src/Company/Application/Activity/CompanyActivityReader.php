<?php

namespace Src\Company\Application\Activity;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Audit\ActivityEntry;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Reads one slice of the activity log of a company and adds what the page shows next to each entry: the actor's name and the title of the job it was about. */
final readonly class CompanyActivityReader
{
    public function __construct(
        private MemberActivity $activity,
        private CompanyMemberAccounts $accounts,
        private JobDirectory $jobs,
    ) {}

    /**
     * @return CursorPage<CompanyActivityRow>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function read(int $companyId, CompanyActivityFilter $filter, CursorRequest $request): CursorPage
    {
        $page = $this->activity->ofCompanyLog($companyId, $filter->userId, $filter->actions(), $filter->from, $filter->to, $request);

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (ActivityEntry $e): ?int => $e->actorType === AuditActorType::User ? $e->actorId : null, $page->items,
        ))));
        $jobIds = array_values(array_unique(array_filter(array_map(
            static fn (ActivityEntry $e): ?int => $e->subjectType === 'job' ? $e->subjectId : null, $page->items,
        ))));
        $names = $userIds === [] ? [] : $this->accounts->summaries($userIds);
        $titles = $jobIds === [] ? [] : $this->jobs->titles($jobIds);

        return new CursorPage(array_map(static fn (ActivityEntry $e): CompanyActivityRow => new CompanyActivityRow(
            $e,
            $e->actorType === AuditActorType::User && $e->actorId !== null ? ($names[$e->actorId] ?? null)?->name : null,
            $e->subjectType === 'job' && $e->subjectId !== null ? ($titles[$e->subjectId] ?? null) : null,
        ), $page->items), $page->nextCursor, $page->perPage);
    }
}
