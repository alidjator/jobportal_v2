<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Job\RequisitionJobs;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Application\Workforce\RequisitionTerms;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceSettings;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/** The Workforce side of the job rules: whether a tenant must open jobs from a requisition, and the reservation of a requisition's hires by its jobs. */
final readonly class RequisitionGateService implements RequisitionGate
{
    public function __construct(
        private TenantRegistry $tenants,
        private Settings $settings,
        private RequisitionRepository $requisitions,
        private RequisitionJobs $jobs,
        private PositionDirectory $positions,
        private Clock $clock,
    ) {}

    public function isRequired(int $companyId): bool
    {
        return $this->tenants->legalEntityOf($companyId) !== null && $this->settings->boolean(WorkforceSettings::REQUISITION_REQUIRED, $companyId);
    }

    public function claim(int $companyId, int $requisitionId, int $vacancies, ?int $exceptJobId = null): RequisitionTerms
    {
        $requisition = $this->requisitions->find($requisitionId, true);

        if ($requisition === null || $requisition->companyId !== $companyId) {
            throw new BusinessRuleViolation('The requisition does not exist for this tenant.', 'invalid-requisition');
        }

        if (! $requisition->status->isOpen()) {
            throw new StateConflict('Jobs are opened only for an approved requisition or one in recruitment.', 'requisition-not-open');
        }

        $others = $this->jobs->openVacancies($requisitionId, $exceptJobId);

        if ($others + $vacancies > $requisition->hires) {
            $left = max($requisition->hires - $others, 0);

            throw new BusinessRuleViolation("The requisition has {$left} hire(s) left to open.", 'requisition-vacancies-exceeded', extensions: ['remaining' => $left]);
        }

        if ($requisition->status === RequisitionStatus::Approved) {
            $this->requisitions->save($requisition->with(['status' => RequisitionStatus::InRecruitment]));
        }

        $position = $this->positions->ofEntity($requisition->legalEntityId, $this->clock->now())[$requisition->positionId] ?? null;

        return new RequisitionTerms($position->title ?? '', $position?->jobRoleId);
    }
}
