<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\Events\ApprovalChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\ApprovalRejected;
use Src\Shared\Application\Approval\ApprovalReturned;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What an account does with the approvals it is part of: reads its own (the steps waiting for it, or the ones it asked for) and decides: approve its place in the step being
 * decided, reject (the subject becomes `rejected`) or return it for revision (the subject goes back to a draft), the last two with a reason of 5 to 500 characters. A decision
 * by a delegate records the approver it stood in for. An account with nothing to do with an approval cannot tell that it exists (404).
 */
final readonly class MyApprovals
{
    private const int REASON_MIN = 5;

    public function __construct(
        private ApprovalInstanceRepository $instances,
        private DecisionAuthority $authority,
        private ApprovalViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<ApprovalView> `approver`: the approvals waiting for the account (newest first); `requester`: the ones it asked for
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function mine(Actor $actor, string $as): array
    {
        $this->member($actor);

        if ($as === 'requester') {
            $ids = $this->instances->ofRequester((int) $actor->id);
        } else {
            $held = $this->heldBy($actor);
            $ids = $this->instances->pendingFor(
                [(int) $actor->id, ...$this->authority->delegatorsToday((int) $actor->id)], $held['group_id'] ?? null, $held['permissions'] ?? [],
            );
        }

        $instances = array_values(array_filter(array_map($this->instances->find(...), $ids)));

        if ($as !== 'requester') {
            // The query matches the slots an account could fill; a delegate or holder still may not be the requester.
            $instances = array_values(array_filter($instances, fn (ApprovalInstance $instance): bool => $this->authority->slotFor($instance, $actor, $this->authority->today()) !== null));
        }

        return $this->views->of($instances, $actor);
    }

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound the approval is missing or the account has nothing to do with it
     */
    public function get(Actor $actor, int $id): ApprovalView
    {
        $this->member($actor);
        $instance = $this->visible($actor, $id, $this->instances->find($id));

        return $this->views->of([$instance], $actor)[0];
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `approval-decision-not-allowed`
     * @throws EntityNotFound
     * @throws StateConflict `approval-not-pending`
     */
    public function approve(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): ApprovalView
    {
        $this->member($actor);

        $this->transactions->run(function () use ($actor, $id, $ipAddress, $userAgent): void {
            $instance = $this->visible($actor, $id, $this->instances->findForUpdate($id));
            $today = $this->authority->today();
            $now = $this->clock->now();
            $decision = $this->authority->slotFor($instance, $actor, $today) ?? $this->notYours($instance);
            $position = $instance->currentStep()?->position;
            $approved = $instance->approve((int) $decision['slot']->id, (int) $actor->id, $decision['onBehalfOf'], $now);
            $this->instances->save($instance);

            $events = [new ApprovalChanged(
                $id, $instance->subjectType, $instance->subjectId, $instance->companyId, 'step_approved', $actor->id,
                ['step' => $position, 'on_behalf_of' => $decision['onBehalfOf']], $ipAddress, $userAgent, $now,
            )];

            if ($approved) {
                $events[] = new ApprovalChanged($id, $instance->subjectType, $instance->subjectId, $instance->companyId, 'granted', $actor->id, [], $ipAddress, $userAgent, $now);
                $events[] = new ApprovalGranted($id, $instance->subjectType, $instance->subjectId, $instance->companyId, $instance->requesterId, $actor->id, $now);
            }

            $this->events->dispatch($events);
        });

        return $this->get($actor, $id);
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `approval-decision-not-allowed`
     * @throws BusinessRuleViolation `approval-reason-required`
     * @throws EntityNotFound
     * @throws StateConflict `approval-not-pending`
     */
    public function reject(Actor $actor, int $id, string $reason, ?string $ipAddress, ?string $userAgent): ApprovalView
    {
        return $this->end($actor, $id, $reason, 'rejected', $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `approval-decision-not-allowed`
     * @throws BusinessRuleViolation `approval-reason-required`
     * @throws EntityNotFound
     * @throws StateConflict `approval-not-pending`
     */
    public function returnForRevision(Actor $actor, int $id, string $reason, ?string $ipAddress, ?string $userAgent): ApprovalView
    {
        return $this->end($actor, $id, $reason, 'returned', $ipAddress, $userAgent);
    }

    private function end(Actor $actor, int $id, string $reason, string $change, ?string $ipAddress, ?string $userAgent): ApprovalView
    {
        $this->member($actor);
        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('A reason of 5 to 500 characters is required.', 'approval-reason-required');
        }

        $this->transactions->run(function () use ($actor, $id, $reason, $change, $ipAddress, $userAgent): void {
            $instance = $this->visible($actor, $id, $this->instances->findForUpdate($id));
            $now = $this->clock->now();
            $this->authority->slotFor($instance, $actor, $this->authority->today()) ?? $this->notYours($instance);

            if ($change === 'rejected') {
                $instance->reject($reason, (int) $actor->id, $now);
            } else {
                $instance->returnForRevision($reason, (int) $actor->id, $now);
            }

            $this->instances->save($instance);

            $outcome = $change === 'rejected'
                ? new ApprovalRejected($id, $instance->subjectType, $instance->subjectId, $instance->companyId, $instance->requesterId, $reason, $actor->id, $now)
                : new ApprovalReturned($id, $instance->subjectType, $instance->subjectId, $instance->companyId, $instance->requesterId, $reason, $actor->id, $now);

            $this->events->dispatch([
                new ApprovalChanged($id, $instance->subjectType, $instance->subjectId, $instance->companyId, $change, $actor->id, ['reason' => $reason], $ipAddress, $userAgent, $now),
                $outcome,
            ]);
        });

        return $this->get($actor, $id);
    }

    /** @throws AccessDenied `not-a-company-member` */
    private function member(Actor $actor): void
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->id === null) {
            throw new AccessDenied('Only a company member takes part in approvals.', 'not-a-company-member');
        }
    }

    /** @return array{group_id?: int, permissions?: list<string>} */
    private function heldBy(Actor $actor): array
    {
        return $this->authority->held($actor);
    }

    /** @throws EntityNotFound */
    private function visible(Actor $actor, int $id, ?ApprovalInstance $instance): ApprovalInstance
    {
        if ($instance === null || ! $this->authority->isInvolved($instance, $actor, $this->authority->today())) {
            throw EntityNotFound::of('Approval', $id);
        }

        return $instance;
    }

    /** @throws AccessDenied `approval-decision-not-allowed` */
    private function notYours(ApprovalInstance $instance): never
    {
        if (! $instance->isPending()) {
            throw new StateConflict('This approval has ended.', 'approval-not-pending');
        }

        throw new AccessDenied('This approval is not waiting for a decision from you.', 'approval-decision-not-allowed');
    }
}
