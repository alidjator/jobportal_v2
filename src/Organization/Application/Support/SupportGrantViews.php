<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Domain\SupportGrant;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;

/** Turns support grants into views with the names of the admin and the granting member, in two reads however many there are. */
final readonly class SupportGrantViews
{
    public function __construct(private AdminAccounts $admins, private CompanyMemberAccounts $accounts, private Clock $clock) {}

    /**
     * @param  list<SupportGrant>  $grants
     * @return list<SupportGrantView>
     */
    public function of(array $grants): array
    {
        $admins = $this->admins->summaries(array_values(array_unique(array_map(static fn (SupportGrant $grant): int => $grant->adminId, $grants))));
        $users = $this->accounts->summaries(array_values(array_unique(array_map(static fn (SupportGrant $grant): int => $grant->grantedByUserId, $grants))));
        $now = $this->clock->now();

        return array_map(static fn (SupportGrant $grant): SupportGrantView => new SupportGrantView(
            $grant, ($admins[$grant->adminId] ?? null)?->name, ($users[$grant->grantedByUserId] ?? null)?->name, $now,
        ), $grants);
    }
}
