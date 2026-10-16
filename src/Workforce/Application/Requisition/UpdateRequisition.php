<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/** Replaces what is written in a draft or rejected requisition; a rejected one becomes a draft again. */
final readonly class UpdateRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRules $rules,
        private RequisitionRepository $requisitions,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-editable`
     * @throws BusinessRuleViolation `invalid-requisition-field`
     */
    public function execute(Actor $actor, int $id, RequisitionInput $input, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);

        $requisition = $this->transactions->run(function () use ($scope, $id, $input): Requisition {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $id);
            }

            if (! $requisition->status->isEditable()) {
                throw new StateConflict('Only a draft or a rejected requisition can be changed.', 'requisition-not-editable');
            }

            $changed = $requisition->with([...$this->rules->fields($requisition->companyId, $requisition->legalEntityId, $input), 'status' => RequisitionStatus::Draft]);
            $this->requisitions->save($changed);

            return $changed;
        });

        $this->recorder->record($requisition, 'updated', $actor->id, $ipAddress, $userAgent);

        return $this->views->detail($requisition, $scope);
    }
}
