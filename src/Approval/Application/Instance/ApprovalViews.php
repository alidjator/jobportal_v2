<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\Slot;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;

/** Turns approvals into views for the account that reads them, with the names of everyone in them in one read. */
final readonly class ApprovalViews
{
    public function __construct(private CompanyMemberAccounts $accounts, private DecisionAuthority $authority) {}

    /**
     * @param  list<ApprovalInstance>  $instances
     * @return list<ApprovalView>
     */
    public function of(array $instances, Actor $actor): array
    {
        $ids = [];

        foreach ($instances as $instance) {
            $ids[$instance->requesterId] = $instance->requesterId;

            if ($instance->outcomeByUserId !== null) {
                $ids[$instance->outcomeByUserId] = $instance->outcomeByUserId;
            }

            foreach ($instance->steps as $step) {
                foreach ($step->slots as $slot) {
                    foreach ($this->people($slot) as $id) {
                        $ids[$id] = $id;
                    }
                }
            }
        }

        $names = [];

        foreach ($ids === [] ? [] : $this->accounts->summaries(array_values($ids)) as $id => $summary) {
            $names[$id] = $summary->name;
        }

        $today = $this->authority->today();

        return array_map(fn (ApprovalInstance $instance): ApprovalView => new ApprovalView(
            $instance, $names, $this->authority->slotFor($instance, $actor, $today) !== null, $instance->isPending() && $this->authority->canReplace($instance, $actor),
        ), $instances);
    }

    /** @return list<int> */
    private function people(Slot $slot): array
    {
        return array_values(array_filter([$slot->userId, $slot->decidedByUserId, $slot->onBehalfOfUserId], static fn (?int $id): bool => $id !== null));
    }
}
