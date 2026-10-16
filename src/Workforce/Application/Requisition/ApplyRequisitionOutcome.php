<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Workforce\Domain\AmendmentStatus;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionAmendment;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/**
 * Moves a requisition, or an amendment of one, on when its approval ends. A requisition is approved, rejected (editable, can be submitted again) or returned (back to a draft); an approved
 * amendment applies its new values, unless the requisition is no longer open. An outcome for an approval the subject no longer waits for is ignored.
 */
final readonly class ApplyRequisitionOutcome
{
    public function __construct(private RequisitionRepository $requisitions, private RequisitionChangeRecorder $recorder, private Clock $clock, private TransactionManager $transactions) {}

    public function requisitionGranted(int $id, int $approvalId, ?int $decidedBy): void
    {
        $this->closeRequisition($id, $approvalId, RequisitionStatus::Approved, 'approved', null, $decidedBy);
    }

    public function requisitionRejected(int $id, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->closeRequisition($id, $approvalId, RequisitionStatus::Rejected, 'rejected', $reason, $decidedBy);
    }

    public function requisitionReturned(int $id, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->closeRequisition($id, $approvalId, RequisitionStatus::Draft, 'returned', $reason, $decidedBy);
    }

    public function amendmentGranted(int $id, int $approvalId, ?int $decidedBy): void
    {
        $result = $this->transactions->run(function () use ($id, $approvalId): ?array {
            $amendment = $this->pendingAmendment($id, $approvalId);
            $requisition = $amendment === null ? null : $this->requisitions->find($amendment->requisitionId, true);

            if ($amendment === null || $requisition === null) {
                return null;
            }

            if (! $requisition->status->isOpen()) {
                $this->requisitions->saveAmendment($amendment->with(['status' => AmendmentStatus::Rejected, 'decidedAt' => $this->clock->now(), 'decisionReason' => 'The requisition is no longer open.']));

                return [$requisition, 'amendment_rejected'];
            }

            $this->requisitions->saveAmendment($amendment->with(['status' => AmendmentStatus::Approved, 'decidedAt' => $this->clock->now()]));
            $changed = $requisition->with([
                'hires' => $amendment->hires ?? $requisition->hires, 'salaryMin' => $amendment->salaryMin ?? $requisition->salaryMin, 'salaryMax' => $amendment->salaryMax ?? $requisition->salaryMax,
            ]);
            $this->requisitions->save($changed);

            return [$changed, 'amendment_approved'];
        });

        if ($result !== null) {
            $this->recorder->record($result[0], $result[1], $decidedBy, detail: ['amendment_id' => $id]);
        }
    }

    public function amendmentRejected(int $id, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->closeAmendment($id, $approvalId, AmendmentStatus::Rejected, 'amendment_rejected', $reason, $decidedBy);
    }

    public function amendmentReturned(int $id, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->closeAmendment($id, $approvalId, AmendmentStatus::Returned, 'amendment_returned', $reason, $decidedBy);
    }

    private function closeRequisition(int $id, int $approvalId, RequisitionStatus $status, string $change, ?string $reason, ?int $decidedBy): void
    {
        $requisition = $this->transactions->run(function () use ($id, $approvalId, $status, $reason): ?Requisition {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || $requisition->status !== RequisitionStatus::PendingApproval || $requisition->approvalId !== $approvalId) {
                return null;
            }

            $decided = $requisition->with(['status' => $status, 'decidedAt' => $this->clock->now(), 'decisionReason' => $reason]);
            $this->requisitions->save($decided);

            return $decided;
        });

        if ($requisition !== null) {
            $this->recorder->record($requisition, $change, $decidedBy, detail: $reason === null ? [] : ['reason' => $reason]);
        }
    }

    private function closeAmendment(int $id, int $approvalId, AmendmentStatus $status, string $change, string $reason, ?int $decidedBy): void
    {
        $requisition = $this->transactions->run(function () use ($id, $approvalId, $status, $reason): ?Requisition {
            $amendment = $this->pendingAmendment($id, $approvalId);

            if ($amendment === null) {
                return null;
            }

            $this->requisitions->saveAmendment($amendment->with(['status' => $status, 'decidedAt' => $this->clock->now(), 'decisionReason' => $reason]));

            return $this->requisitions->find($amendment->requisitionId);
        });

        if ($requisition !== null) {
            $this->recorder->record($requisition, $change, $decidedBy, detail: ['amendment_id' => $id, 'reason' => $reason]);
        }
    }

    private function pendingAmendment(int $id, int $approvalId): ?RequisitionAmendment
    {
        $amendment = $this->requisitions->findAmendment($id, true);

        return $amendment !== null && $amendment->status === AmendmentStatus::PendingApproval && $amendment->approvalId === $approvalId ? $amendment : null;
    }
}
