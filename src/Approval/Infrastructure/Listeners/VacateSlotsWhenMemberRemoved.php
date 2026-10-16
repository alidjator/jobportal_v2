<?php

namespace Src\Approval\Infrastructure\Listeners;

use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/**
 * An approver left their tenant: every place of theirs that waits in a running approval becomes vacant until a company admin or a holder of `group.approvals.manage` names a
 * replacement (`docs/design/erp-requisitions.md` section 4). Idempotent.
 */
final class VacateSlotsWhenMemberRemoved
{
    public function __construct(
        private readonly ApprovalInstanceRepository $instances,
        private readonly EventDispatcher $events,
        private readonly Clock $clock,
    ) {}

    public function handle(CompanyMemberRemoved $event): void
    {
        foreach ($this->instances->pendingWithSlotOf($event->userId) as $id) {
            $instance = $this->instances->find($id);

            if ($instance === null || $instance->vacate($event->userId) === 0) {
                continue;
            }

            $this->instances->save($instance);
            $this->events->dispatch([new ApprovalChanged(
                $id, $instance->subjectType, $instance->subjectId, $instance->companyId, 'approver_vacated', null, ['user_id' => $event->userId], null, null, $this->clock->now(),
            )]);
        }
    }
}
