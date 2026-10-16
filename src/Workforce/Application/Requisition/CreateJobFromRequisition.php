<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\RequisitionJobCreator;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Domain\RequisitionRepository;

/**
 * Opens a draft job for an approved requisition. The title (editable later) defaults to the title of the position and the vacancies to the hires not yet opened; the job takes the job
 * role of the position, and the member needs the permission to create jobs as for any job.
 */
final readonly class CreateJobFromRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRepository $requisitions,
        private RequisitionJobCreator $creator,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
    ) {}

    /**
     * @return int the id of the new draft job
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-open`
     * @throws BusinessRuleViolation `requisition-vacancies-exceeded` (also when nothing is left to open) or `invalid-job`
     */
    public function execute(Actor $actor, int $id, ?string $title, ?int $vacancies, ?string $ipAddress, ?string $userAgent): int
    {
        $scope = $this->access->read($actor, PlanSide::Company);
        $requisition = $this->requisitions->find($id);

        if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
            throw EntityNotFound::of('Requisition', $id);
        }

        if (! $requisition->status->isOpen()) {
            throw new StateConflict('Jobs are opened only for an approved requisition or one in recruitment.', 'requisition-not-open');
        }

        $detail = $this->views->detail($requisition, $scope);
        $left = max($requisition->hires - (int) $detail->openVacancies, 0);

        if ($vacancies === null && $left === 0) {
            throw new BusinessRuleViolation('All the hires of this requisition are already open in jobs.', 'requisition-vacancies-exceeded', extensions: ['remaining' => 0]);
        }

        $jobId = $this->creator->create($actor, $id, $title ?? $detail->position->title ?? 'Job opening', $vacancies ?? $left);

        $this->recorder->record($requisition, 'job_opened', $actor->id, $ipAddress, $userAgent, ['job_id' => $jobId]);

        return $jobId;
    }
}
