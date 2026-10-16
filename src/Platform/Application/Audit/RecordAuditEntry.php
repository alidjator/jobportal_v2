<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Organization\GroupDirectory;

/**
 * Writes the audit log: the Platform implementation of the Shared `AuditTrail` port. An entry that names no company gets one for the company activity
 * log: the company the entry is about when its subject is a company (a platform admin's action on it included), otherwise the company the acting
 * member belongs to now. An entry that names no group gets one for the group activity log: the group it is about when its subject is a group, otherwise
 * the group of its company (none for an external tenant).
 */
final readonly class RecordAuditEntry implements AuditTrail
{
    private const string COMPANY_SUBJECT_TYPE = 'company';

    private const string GROUP_SUBJECT_TYPE = 'organization_group';

    public function __construct(
        private AuditLogRepository $audit,
        private CompanyMembershipResolver $memberships,
        private GroupDirectory $groups,
    ) {}

    public function record(AuditEntry $entry): void
    {
        $companyId = $entry->companyId ?? $this->companyOf($entry);
        $groupId = $entry->groupId ?? $this->groupOf($entry, $companyId);

        $this->audit->append($companyId === $entry->companyId && $groupId === $entry->groupId ? $entry : new AuditEntry(
            $entry->actorType, $entry->actorId, $entry->action, $entry->subjectType, $entry->subjectId, $entry->metadata, $entry->ipAddress, $entry->userAgent, $entry->occurredAt,
            $companyId, $groupId,
        ));
    }

    private function companyOf(AuditEntry $entry): ?int
    {
        return match (true) {
            $entry->subjectType === self::COMPANY_SUBJECT_TYPE && $entry->subjectId !== null => $entry->subjectId,
            $entry->actorType === AuditActorType::User && $entry->actorId !== null => $this->memberships->forUser($entry->actorId)?->companyId,
            default => null,
        };
    }

    private function groupOf(AuditEntry $entry, ?int $companyId): ?int
    {
        if ($entry->subjectType === self::GROUP_SUBJECT_TYPE && $entry->subjectId !== null) {
            return $entry->subjectId;
        }

        return $companyId === null ? null : $this->groups->groupOfCompany($companyId);
    }
}
