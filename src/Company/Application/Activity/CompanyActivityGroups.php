<?php

namespace Src\Company\Application\Activity;

use Src\Shared\Application\Audit\AuditAction;

/** The groups the company activity log is filtered by (`filter[group]`), each a set of audit actions. An action in no group shows only without a group filter. */
final class CompanyActivityGroups
{
    /** @var array<string, list<string>> */
    private const array GROUPS = [
        'sign_in' => [AuditAction::LOGIN, AuditAction::LOGIN_FAILED],
        'security' => [
            AuditAction::PASSWORD_RESET, AuditAction::PASSWORD_CHANGED, AuditAction::SIGNED_OUT_EVERYWHERE, AuditAction::TWO_FACTOR_ENABLED,
            AuditAction::TWO_FACTOR_DISABLED, AuditAction::TWO_FACTOR_RESET, AuditAction::RECOVERY_CODE_USED, AuditAction::RECOVERY_CODES_REGENERATED,
            AuditAction::COMPANY_MEMBER_TWO_FACTOR_RESET,
        ],
        'data_access' => [
            AuditAction::APPLICATION_RESUME_DOWNLOADED, AuditAction::CANDIDATE_RESUME_DOWNLOADED, AuditAction::ASSESSMENT_REPORT_DOWNLOADED,
            AuditAction::INTERVIEW_RECORDING_DOWNLOADED, AuditAction::OFFER_LETTER_DOWNLOADED,
        ],
        'export' => [AuditAction::EXPORT_APPLICANTS, AuditAction::EXPORT_HIRES, AuditAction::COMPANY_ACTIVITY_EXPORTED],
        'team' => [
            AuditAction::COMPANY_MEMBER_JOINED, AuditAction::COMPANY_MEMBER_REMOVED, AuditAction::COMPANY_MEMBER_ROLE_CHANGED,
            AuditAction::COMPANY_MEMBER_PERMISSIONS_CHANGED, AuditAction::COMPANY_MEMBER_RENAMED, AuditAction::COMPANY_MEMBER_SUSPENDED,
            AuditAction::COMPANY_MEMBER_REACTIVATED, AuditAction::COMPANY_OWNER_TRANSFERRED, AuditAction::COMPANY_INVITATION_SENT,
            AuditAction::COMPANY_INVITATION_REVOKED, AuditAction::COMPANY_INVITATION_UPDATED, AuditAction::COMPANY_INVITATION_RESENT,
        ],
        'jobs' => [
            AuditAction::JOB_ASSIGNEES_CHANGED, AuditAction::JOB_PUBLISHED, AuditAction::JOB_CLOSED, AuditAction::JOB_REOPENED,
            AuditAction::JOB_SENT_FOR_REVIEW, AuditAction::JOB_EXPIRED, AuditAction::JOB_APPROVED, AuditAction::JOB_REJECTED,
            AuditAction::JOB_TAKEN_DOWN, AuditAction::JOB_RESTORED,
        ],
        'settings' => [
            AuditAction::COMPANY_SETTINGS_UPDATED, AuditAction::SETTING_UPDATED, AuditAction::SETTING_RESET,
            AuditAction::COMPANY_INVITATION_EMAIL_DOMAINS_CHANGED, AuditAction::COMPANY_NAME_CHANGE_REQUESTED, AuditAction::COMPANY_NAME_CHANGE_CANCELLED,
            AuditAction::COMPANY_NAME_CHANGE_APPROVED, AuditAction::COMPANY_NAME_CHANGE_REJECTED,
        ],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::GROUPS);
    }

    /** @return list<string> the actions of the group; empty for a group that does not exist */
    public static function actions(string $group): array
    {
        return self::GROUPS[$group] ?? [];
    }
}
