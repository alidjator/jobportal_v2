<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Application\Flow\ApprovalFlows;
use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\ApproverKind;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Approval\Domain\Flow;
use Src\Approval\Domain\FlowStep;
use Src\Approval\Domain\InstanceStatus;
use Src\Approval\Domain\InstanceStep;
use Src\Approval\Domain\Slot;
use Src\Approval\Domain\SlotKind;
use Src\Approval\Domain\SlotStatus;
use Src\Approval\Domain\StepMode;
use Src\Approval\Domain\StepStatus;
use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Approval's side of the `Approvals` port. A submission copies the flow that applies to the tenant (its own, else its group's) as the steps whose conditions hold of the subject's
 * facts, turns each approver into a slot (a named account, a relative role resolved by the subject's context now, or any holder of a group permission), and starts the first step.
 * The requester never decides: their own slots are dropped, and a step they alone could approve is refused.
 */
final readonly class SubmitForApproval implements Approvals
{
    public function __construct(
        private ApprovalSubjectCatalog $catalog,
        private ApprovalFlows $flows,
        private GroupDirectory $groups,
        private ApprovalInstanceRepository $instances,
        private ConditionEvaluator $conditions,
        private CompanyMembershipResolver $memberships,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function submit(ApprovalSubmission $submission): int
    {
        $this->catalog->find($submission->subjectType) ?? throw new BusinessRuleViolation('The subject type :type is not registered for approval.', 'approval-unknown-subject-type', parameters: ['type' => $submission->subjectType]);
        $groupId = $this->groups->groupOfCompany($submission->companyId) ?? throw new BusinessRuleViolation('Approvals belong to the tenants of an ERP group.', 'approval-not-internal-tenant');

        if ($this->instances->pendingOfSubject($submission->subjectType, $submission->subjectId) !== null) {
            throw new StateConflict('This subject is already in approval.', 'approval-already-pending');
        }

        $flow = $this->flows->resolve($submission->companyId, $groupId, $submission->subjectType)
            ?? throw new StateConflict('Neither the tenant nor its group has an approval flow for this kind of request.', 'approval-flow-missing');

        $now = $this->clock->now();
        $instance = new ApprovalInstance(
            null, $submission->subjectType, $submission->subjectId, $submission->companyId, $groupId, $submission->requesterId, $submission->title, $submission->facts,
            InstanceStatus::Pending, $this->steps($flow, $submission), $now,
        );
        $instance->start($now);
        $instance = $this->instances->create($instance);

        $this->events->dispatch([new ApprovalChanged(
            $instance->id, $instance->subjectType, $instance->subjectId, $instance->companyId, 'submitted', $instance->requesterId,
            ['steps' => count($instance->steps), 'title' => $instance->title], null, null, $now,
        )]);

        if ($instance->status === InstanceStatus::Approved) {
            // No step applied to this subject: there is nothing to approve.
            $this->events->dispatch([
                new ApprovalChanged($instance->id, $instance->subjectType, $instance->subjectId, $instance->companyId, 'granted', null, ['automatic' => true], null, null, $now),
                new ApprovalGranted((int) $instance->id, $instance->subjectType, $instance->subjectId, $instance->companyId, $instance->requesterId, null, $now),
            ]);
        }

        return (int) $instance->id;
    }

    public function cancel(string $subjectType, int $subjectId, int $actorUserId): void
    {
        $instance = $this->instances->pendingOfSubject($subjectType, $subjectId);

        if ($instance === null) {
            return;
        }

        $now = $this->clock->now();
        $instance->cancel($now);
        $this->instances->save($instance);

        $this->events->dispatch([new ApprovalChanged($instance->id, $subjectType, $subjectId, $instance->companyId, 'cancelled', $actorUserId, [], null, null, $now)]);
    }

    /** @return list<InstanceStep> */
    private function steps(Flow $flow, ApprovalSubmission $submission): array
    {
        $steps = [];

        foreach ($flow->steps as $step) {
            if (! $this->conditions->allHold($step->conditions, $submission->facts)) {
                continue;
            }

            $steps[] = new InstanceStep(null, $step->position, $step->mode, StepStatus::Waiting, $this->slots($step, $submission));
        }

        return $steps;
    }

    /**
     * @return list<Slot>
     *
     * @throws BusinessRuleViolation `approval-requester-only-approver`
     */
    private function slots(FlowStep $step, ApprovalSubmission $submission): array
    {
        $slots = [];

        foreach ($step->approvers as $approver) {
            $slots = [...$slots, ...match ($approver->kind) {
                ApproverKind::Member => [$this->userSlot((int) $approver->value)],
                ApproverKind::Relative => $this->relativeSlots($submission->relativeApprovers[$approver->value] ?? []),
                ApproverKind::GroupRole => [Slot::role($approver->value)],
            }];
        }

        $others = array_values(array_filter($slots, static fn (Slot $slot): bool => $slot->kind !== SlotKind::User || $slot->userId !== $submission->requesterId));

        // An `all` step that names the requester could never be completed, and a step only the requester could approve would approve itself.
        if ($others === [] || ($step->mode === StepMode::All && count($others) !== count($slots))) {
            throw new BusinessRuleViolation('Step :position could only be approved by the requester.', 'approval-requester-only-approver', parameters: ['position' => $step->position]);
        }

        return $others;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<Slot>
     */
    private function relativeSlots(array $userIds): array
    {
        return $userIds === [] ? [Slot::user(null)] : array_map($this->userSlot(...), array_values(array_unique($userIds)));
    }

    private function userSlot(int $userId): Slot
    {
        $slot = Slot::user($userId);

        // An account that is no longer a member of any tenant cannot decide: its place waits for a replacement.
        if ($this->memberships->forUser($userId) === null) {
            $slot->status = SlotStatus::Vacant;
        }

        return $slot;
    }
}
