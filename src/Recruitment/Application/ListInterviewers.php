<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * Who may sit on an interview panel, for the member who schedules interviews: the active members who hold `interviews.participate` and the company admins, the same set
 * the scheduler accepts as main interviewer and panel members (`phase-8-interviewers.md` section 1). A member who cannot schedule has no use for the list and cannot read it.
 */
final readonly class ListInterviewers
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyTeam $team,
        private CompanyMemberAccounts $accounts,
    ) {}

    /**
     * @return list<array{id: int, name: string}> by name
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function execute(Actor $actor): array
    {
        $member = $this->access->permitted($actor, CompanyPermission::InterviewsManage);
        $people = [];

        foreach ($this->accounts->summaries($this->team->interviewerUserIds($member->companyId)) as $account) {
            if ($account->status === 'active') {
                $people[] = ['id' => $account->userId, 'name' => $account->name];
            }
        }

        usort($people, static fn (array $a, array $b): int => [mb_strtolower($a['name']), $a['id']] <=> [mb_strtolower($b['name']), $b['id']]);

        return $people;
    }
}
