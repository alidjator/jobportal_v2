<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\ActivityEntry;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * What a member of the actor's company did, from the audit log (the actions whose actor is the member; an account belongs to one company), newest
 * first, as far back as the log is kept. Each entry shows the IP address and user agent (so a company admin can spot a suspicious sign-in).
 */
final readonly class ListMemberActivity
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyMemberRepository $members,
        private MemberActivity $activity,
        private JobDirectory $jobs,
    ) {}

    /**
     * @return CursorPage<ActivityRow>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function execute(Actor $actor, int $userId, ?string $action, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage
    {
        $companyId = (int) $this->guard->company($actor)->id;

        if ($this->members->find($companyId, $userId) === null) {
            throw EntityNotFound::of('Member', $userId);
        }

        $page = $this->activity->ofMember($userId, $action, $from, $to, $request);
        $jobIds = array_values(array_unique(array_filter(
            array_map(static fn (ActivityEntry $e): ?int => $e->subjectType === 'job' ? $e->subjectId : null, $page->items),
        )));
        $titles = $jobIds === [] ? [] : $this->jobs->titles($jobIds);

        return new CursorPage(
            array_map(static fn (ActivityEntry $e): ActivityRow => new ActivityRow($e, $e->subjectType === 'job' && $e->subjectId !== null ? ($titles[$e->subjectId] ?? null) : null), $page->items),
            $page->nextCursor,
            $page->perPage,
        );
    }
}
