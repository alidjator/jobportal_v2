<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/** Starts a requisition of the member's own tenant as a draft with its number; the requester is the member. It uses no quota until it is submitted. */
final readonly class CreateRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRules $rules,
        private RequisitionRepository $requisitions,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private RequisitionNumbers $numbers,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws BusinessRuleViolation `invalid-requisition-field`
     * @throws StateConflict `requisition-number-taken`
     */
    public function execute(Actor $actor, RequisitionInput $input, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);
        $companyId = (int) $scope->companyId;
        $entityId = $scope->legalEntityIds[0];

        $fields = $this->rules->fields($companyId, $entityId, $input);

        // The number is taken in the transaction that stores the requisition, so a failed save gives it back.
        $requisition = $this->transactions->run(fn (): Requisition => $this->requisitions->create(new Requisition(...[
            'id' => null, 'companyId' => $companyId, 'legalEntityId' => $entityId, 'status' => RequisitionStatus::Draft, 'requesterId' => (int) $actor->id,
            ...$fields, 'number' => $this->numbers->forNew($companyId, $input->number),
        ])));

        $this->recorder->record($requisition, 'created', $actor->id, $ipAddress, $userAgent);

        return $this->views->detail($requisition, $scope);
    }
}
