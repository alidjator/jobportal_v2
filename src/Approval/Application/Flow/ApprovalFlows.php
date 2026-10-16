<?php

namespace Src\Approval\Application\Flow;

use Src\Approval\Domain\Events\ApprovalFlowChanged;
use Src\Approval\Domain\Flow;
use Src\Approval\Domain\FlowOwner;
use Src\Approval\Domain\FlowRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The approval flows of a group (its default) and of a tenant (which replaces the default for itself), one per subject type. The flow that applies to a tenant is its own,
 * else its group's (`resolve`, which the submission of a subject uses); without either there is no flow and a submission is refused.
 */
final readonly class ApprovalFlows
{
    public function __construct(
        private ApprovalFlowAccess $access,
        private ApprovalSubjectCatalog $catalog,
        private FlowRepository $flows,
        private FlowRules $rules,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * Every subject type with the flow that applies to the actor's group.
     *
     * @return list<FlowView>
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     */
    public function ofGroup(Actor $actor): array
    {
        $owner = $this->access->group($actor, false);
        $own = $this->byType($this->flows->ofOwner($owner));

        return array_map(fn (ApprovalSubjectType $type): FlowView => new FlowView($type, $own[$type->code] ?? null, isset($own[$type->code]) ? 'group' : 'none'), $this->catalog->all());
    }

    /**
     * Every subject type with the flow that applies to the actor's tenant and where it comes from.
     *
     * @return list<FlowView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-internal-tenant`
     */
    public function ofCompany(Actor $actor): array
    {
        [$owner, $groupId] = $this->access->company($actor, false);
        $own = $this->byType($this->flows->ofOwner($owner));
        $inherited = $this->byType($this->flows->ofOwner(FlowOwner::group($groupId)));

        return array_map(static fn (ApprovalSubjectType $type): FlowView => isset($own[$type->code])
            ? new FlowView($type, $own[$type->code], 'company')
            : (isset($inherited[$type->code]) ? new FlowView($type, $inherited[$type->code], 'group') : new FlowView($type, null, 'none')), $this->catalog->all());
    }

    /** The flow that applies to the tenant for the subject type: its own, else its group's; null when neither exists. */
    public function resolve(int $companyId, int $groupId, string $subjectType): ?Flow
    {
        return $this->flows->find(FlowOwner::company($companyId), $subjectType) ?? $this->flows->find(FlowOwner::group($groupId), $subjectType);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the subject type is not registered
     * @throws BusinessRuleViolation `invalid-flow-steps`, `invalid-flow-condition` or `invalid-flow-approver`
     */
    public function saveForGroup(Actor $actor, string $subjectType, FlowInput $input, ?string $ipAddress, ?string $userAgent): Flow
    {
        $owner = $this->access->group($actor, true);

        return $this->save($actor, $owner, $owner->id, $subjectType, $input, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-an-internal-tenant`
     * @throws EntityNotFound the subject type is not registered
     * @throws BusinessRuleViolation `invalid-flow-steps`, `invalid-flow-condition` or `invalid-flow-approver`
     */
    public function saveForCompany(Actor $actor, string $subjectType, FlowInput $input, ?string $ipAddress, ?string $userAgent): Flow
    {
        [$owner, $groupId] = $this->access->company($actor, true);

        return $this->save($actor, $owner, $groupId, $subjectType, $input, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied
     * @throws EntityNotFound the subject type is not registered or the group has no flow for it
     */
    public function removeForGroup(Actor $actor, string $subjectType, ?string $ipAddress, ?string $userAgent): void
    {
        $this->remove($actor, $this->access->group($actor, true), $subjectType, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied
     * @throws EntityNotFound the subject type is not registered or the tenant has no flow of its own for it
     */
    public function removeForCompany(Actor $actor, string $subjectType, ?string $ipAddress, ?string $userAgent): void
    {
        $this->remove($actor, $this->access->company($actor, true)[0], $subjectType, $ipAddress, $userAgent);
    }

    private function save(Actor $actor, FlowOwner $owner, int $groupId, string $subjectType, FlowInput $input, ?string $ipAddress, ?string $userAgent): Flow
    {
        $type = $this->catalog->find($subjectType) ?? throw EntityNotFound::of('Approval subject type', $subjectType);
        $this->rules->assertValid($owner, $groupId, $type, $input->steps);

        $flow = $this->flows->save(new Flow(null, $owner, $subjectType, $input->steps));
        $this->record($actor, $owner, $subjectType, 'saved', count($flow->steps), $ipAddress, $userAgent);

        return $flow;
    }

    private function remove(Actor $actor, FlowOwner $owner, string $subjectType, ?string $ipAddress, ?string $userAgent): void
    {
        $this->catalog->find($subjectType) ?? throw EntityNotFound::of('Approval subject type', $subjectType);

        if (! $this->flows->remove($owner, $subjectType)) {
            throw EntityNotFound::of('Approval flow', $subjectType);
        }

        $this->record($actor, $owner, $subjectType, 'removed', 0, $ipAddress, $userAgent);
    }

    private function record(Actor $actor, FlowOwner $owner, string $subjectType, string $change, int $steps, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new ApprovalFlowChanged($owner->type->value, $owner->id, $subjectType, $change, $steps, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }

    /**
     * @param  list<Flow>  $flows
     * @return array<string, Flow>
     */
    private function byType(array $flows): array
    {
        $byType = [];

        foreach ($flows as $flow) {
            $byType[$flow->subjectType] = $flow;
        }

        return $byType;
    }
}
