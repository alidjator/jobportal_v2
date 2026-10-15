<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/**
 * Writes the audit log: the Platform implementation of the Shared `AuditTrail` port. An entry that names no company gets one for the company activity
 * log: the company the entry is about when its subject is a company (a platform admin's action on it included), otherwise the company the acting
 * member belongs to now.
 */
final readonly class RecordAuditEntry implements AuditTrail
{
    private const string COMPANY_SUBJECT_TYPE = 'company';

    public function __construct(
        private AuditLogRepository $audit,
        private CompanyMembershipResolver $memberships,
    ) {}

    public function record(AuditEntry $entry): void
    {
        $this->audit->append($entry->companyId === null ? $this->withCompany($entry) : $entry);
    }

    private function withCompany(AuditEntry $entry): AuditEntry
    {
        $companyId = match (true) {
            $entry->subjectType === self::COMPANY_SUBJECT_TYPE && $entry->subjectId !== null => $entry->subjectId,
            $entry->actorType === AuditActorType::User && $entry->actorId !== null => $this->memberships->forUser($entry->actorId)?->companyId,
            default => null,
        };

        return $companyId === null ? $entry : new AuditEntry(
            $entry->actorType, $entry->actorId, $entry->action, $entry->subjectType, $entry->subjectId, $entry->metadata, $entry->ipAddress, $entry->userAgent, $entry->occurredAt, $companyId,
        );
    }
}
