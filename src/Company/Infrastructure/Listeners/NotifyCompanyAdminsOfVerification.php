<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Application\CompanyNotificationType;
use Src\Company\Application\NotifyCompanyAdmins;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\Events\CompanyVerificationGranted;
use Src\Company\Domain\Events\CompanyVerificationWithdrawn;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the company admins what happened to their verification: a document approved or rejected, the badge granted or withdrawn. Runs on
 * the `default` queue, retried 3 times with a growing delay, after the decision was saved: a failure here never undoes a decision. The
 * notifications carry only keys and codes, never a file name, a company name or an admin name.
 */
final class NotifyCompanyAdminsOfVerification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyCompanyAdmins $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onDocumentApproved(CompanyDocumentApproved $event): void
    {
        $this->notify->execute($event->companyId, CompanyNotificationType::DOCUMENT_APPROVED, [
            'document_type_id' => $event->documentTypeId,
            'document_type_slug' => $event->documentTypeSlug,
        ]);
    }

    public function onDocumentRejected(CompanyDocumentRejected $event): void
    {
        $this->notify->execute($event->companyId, CompanyNotificationType::DOCUMENT_REJECTED, [
            'document_type_id' => $event->documentTypeId,
            'document_type_slug' => $event->documentTypeSlug,
            'reason' => $event->reason,
            'note' => $event->note,
        ]);
    }

    public function onVerificationGranted(CompanyVerificationGranted $event): void
    {
        $this->notify->execute($event->companyId, CompanyNotificationType::VERIFIED);
    }

    public function onVerificationWithdrawn(CompanyVerificationWithdrawn $event): void
    {
        $this->notify->execute($event->companyId, CompanyNotificationType::VERIFICATION_WITHDRAWN);
    }
}
