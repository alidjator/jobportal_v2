<?php

namespace Src\Shared\Application\Audit;

/** The audit action names recorded so far. Names are `area.event` in lower snake case. */
final class AuditAction
{
    public const string LOGIN = 'auth.login';

    public const string LOGIN_FAILED = 'auth.login_failed';

    public const string PASSWORD_RESET = 'auth.password_reset';

    public const string PASSWORD_CHANGED = 'auth.password_changed';

    public const string SIGNED_OUT_EVERYWHERE = 'auth.signed_out_everywhere';

    public const string TWO_FACTOR_ENABLED = 'auth.two_factor_enabled';

    public const string RECOVERY_CODE_USED = 'auth.recovery_code_used';

    public const string RECOVERY_CODES_REGENERATED = 'auth.recovery_codes_regenerated';

    public const string TWO_FACTOR_RESET = 'auth.two_factor_reset';

    public const string TWO_FACTOR_DISABLED = 'auth.two_factor_disabled';

    public const string ADMIN_ROLE_CREATED = 'admin.role_created';

    public const string ADMIN_ROLE_PERMISSIONS_CHANGED = 'admin.role_permissions_changed';

    public const string ADMIN_ROLE_DELETED = 'admin.role_deleted';

    public const string ADMIN_ROLE_CHANGED = 'admin.role_changed';

    public const string ADMIN_SUSPENDED = 'admin.suspended';

    public const string ADMIN_REACTIVATED = 'admin.reactivated';

    public const string ADMIN_PASSWORD_RESET = 'admin.password_reset';

    public const string PRIVACY_DATA_EXPORTED = 'privacy.data_exported';

    public const string ACCOUNT_DELETION_REQUESTED = 'account.deletion_requested';

    public const string ACCOUNT_DELETION_CANCELLED = 'account.deletion_cancelled';

    public const string ACCOUNT_ANONYMIZED = 'account.anonymized';

    public const string AUDIT_PURGED = 'audit.purged';

    public const string SETTING_UPDATED = 'settings.updated';

    public const string SETTING_RESET = 'settings.reset';

    public const string EMAIL_TEMPLATE_UPDATED = 'email_template.updated';

    public const string EMAIL_TEMPLATE_RESET = 'email_template.reset';

    public const string COMPANY_CREATED = 'company.created';

    public const string COMPANY_SUSPENDED = 'company.suspended';

    public const string COMPANY_RESTORED = 'company.restored';

    public const string COMPANY_INVITATION_SENT = 'company.invitation_sent';

    public const string COMPANY_INVITATION_REVOKED = 'company.invitation_revoked';

    public const string COMPANY_MEMBER_JOINED = 'company.member_joined';

    public const string COMPANY_MEMBER_REMOVED = 'company.member_removed';

    public const string COMPANY_MEMBER_ROLE_CHANGED = 'company.member_role_changed';

    public const string COMPANY_MEMBER_PERMISSIONS_CHANGED = 'company.member_permissions_changed';

    public const string COMPANY_MEMBER_RENAMED = 'company.member_renamed';

    public const string COMPANY_MEMBER_SUSPENDED = 'company.member_suspended';

    public const string COMPANY_MEMBER_REACTIVATED = 'company.member_reactivated';

    public const string COMPANY_OWNER_TRANSFERRED = 'company.owner_transferred';

    public const string COMPANY_INVITATION_UPDATED = 'company.invitation_updated';

    public const string COMPANY_INVITATION_RESENT = 'company.invitation_resent';

    public const string COMPANY_INVITATION_EMAIL_DOMAINS_CHANGED = 'company.invitation_email_domains_changed';

    public const string COMPANY_MESSAGE_DEFAULTS_CHANGED = 'company.message_defaults_changed';

    public const string COMPANY_DOCUMENT_SUBMITTED = 'company.document_submitted';

    public const string COMPANY_DOCUMENT_DELETED = 'company.document_deleted';

    public const string COMPANY_MEMBER_TWO_FACTOR_RESET = 'company.member_two_factor_reset';

    public const string COMPANY_ACTIVITY_EXPORTED = 'company.activity_exported';

    public const string COMPANY_SETTINGS_UPDATED = 'company.settings_updated';

    public const string APPLICATION_RESUME_DOWNLOADED = 'application.resume_downloaded';

    public const string CANDIDATE_RESUME_DOWNLOADED = 'candidate.resume_downloaded';

    public const string ASSESSMENT_REPORT_DOWNLOADED = 'assessment.report_downloaded';

    public const string INTERVIEW_RECORDING_DOWNLOADED = 'interview.recording_downloaded';

    public const string JOB_ASSIGNEES_CHANGED = 'job.assignees_changed';

    public const string JOB_PUBLISHED = 'job.published';

    public const string JOB_CLOSED = 'job.closed';

    public const string JOB_REOPENED = 'job.reopened';

    public const string JOB_SENT_FOR_REVIEW = 'job.sent_for_review';

    public const string JOB_EXPIRED = 'job.expired';

    public const string JOB_APPROVED = 'job.approved';

    public const string JOB_REJECTED = 'job.rejected';

    public const string EXPORT_APPLICANTS = 'export.applicants';

    public const string EXPORT_HIRES = 'export.hires';

    public const string APPLICATION_WITHDRAWN_BY_COMPANY = 'recruitment.application_withdrawn_by_company';

    public const string HIRE_UPDATED = 'recruitment.hire_updated';

    public const string JOB_FILLED = 'job.filled';

    public const string JOB_TAKEN_DOWN = 'job.taken_down';

    public const string JOB_RESTORED = 'job.restored';

    public const string JOB_REPORTS_DISMISSED = 'job.reports_dismissed';

    public const string JOB_HOLD_RELEASED = 'job.hold_released';

    public const string COMPANY_NAME_CHANGE_REQUESTED = 'company.name_change_requested';

    public const string COMPANY_NAME_CHANGE_CANCELLED = 'company.name_change_cancelled';

    public const string COMPANY_NAME_CHANGE_APPROVED = 'company.name_change_approved';

    public const string COMPANY_NAME_CHANGE_REJECTED = 'company.name_change_rejected';

    public const string COMPANY_NAME_CHANGE_DOCUMENT_VIEWED = 'company.name_change_document_viewed';

    public const string COMPANY_DOCUMENT_APPROVED = 'company.document_approved';

    public const string COMPANY_DOCUMENT_REJECTED = 'company.document_rejected';

    public const string COMPANY_DOCUMENT_VIEWED = 'company.document_viewed';

    public const string COMPANY_DOCUMENT_TYPE_CREATED = 'company_document_type.created';

    public const string COMPANY_DOCUMENT_TYPE_UPDATED = 'company_document_type.updated';

    public const string CAMPAIGN_CREATED = 'campaign.created';

    public const string CAMPAIGN_UPDATED = 'campaign.updated';

    public const string CAMPAIGN_DELETED = 'campaign.deleted';

    public const string NEWSLETTER_ISSUE_SENT = 'newsletter.issue_sent';

    public const string NEWSLETTER_SUBSCRIBER_DELETED = 'newsletter.subscriber_deleted';

    public const string REVIEW_APPROVED = 'review.approved';

    public const string REVIEW_REJECTED = 'review.rejected';

    public const string REVIEW_REMOVED = 'review.removed';

    public const string REVIEW_REPLY_APPROVED = 'review.reply_approved';

    public const string REVIEW_REPLY_REJECTED = 'review.reply_rejected';

    public const string REVIEW_REPLY_REMOVED = 'review.reply_removed';

    public const string REVIEW_REPORT_DISMISSED = 'review.report_dismissed';

    public const string ASSESSMENT_PROVIDER_REGISTERED = 'assessment.provider_registered';

    public const string ASSESSMENT_PSYCHOLOGIST_INVITED = 'assessment.psychologist_invited';

    public const string ASSESSMENT_PSYCHOLOGIST_JOINED = 'assessment.psychologist_joined';

    public const string ASSESSMENT_PSYCHOLOGIST_CREDENTIALS_CHANGED = 'assessment.psychologist_credentials_changed';

    public const string ASSESSMENT_PSYCHOLOGIST_SUSPENDED = 'assessment.psychologist_suspended';

    public const string ASSESSMENT_PSYCHOLOGIST_RESTORED = 'assessment.psychologist_restored';

    public const string ASSESSMENT_PSYCHOLOGIST_TWO_FACTOR_RESET = 'assessment.psychologist_two_factor_reset';

    public const string ASSESSMENT_REPORT_SIGNED = 'assessment.report_signed';

    public const string ASSESSMENT_REPORT_AUTO_ISSUED = 'assessment.report_auto_issued';

    public const string ASSESSMENT_REPORT_REVISED = 'assessment.report_revised';

    public const string ASSESSMENT_PROVIDER_AUTO_ISSUE_CHANGED = 'assessment_provider.auto_issue_changed';

    public const string GROUP_CREATED = 'group.created';

    public const string GROUP_SUSPENDED = 'group.suspended';

    public const string GROUP_RESTORED = 'group.restored';

    public const string GROUP_LEGAL_ENTITY_CREATED = 'group.legal_entity_created';

    public const string GROUP_LEGAL_ENTITY_UPDATED = 'group.legal_entity_updated';

    public const string GROUP_LEGAL_ENTITY_DEACTIVATED = 'group.legal_entity_deactivated';

    public const string GROUP_LEGAL_ENTITY_REACTIVATED = 'group.legal_entity_reactivated';

    public const string GROUP_TENANT_LINKED = 'group.tenant_linked';

    public const string GROUP_TENANT_UNLINKED = 'group.tenant_unlinked';

    public const string GROUP_ROLE_GRANTED = 'group.role_granted';

    public const string GROUP_ROLE_CHANGED = 'group.role_changed';

    public const string GROUP_ROLE_REMOVED = 'group.role_removed';

    public const string GROUP_SETTINGS_UPDATED = 'group.settings_updated';

    public const string GROUP_SUPPORT_GRANTED = 'group.support_granted';

    public const string GROUP_SUPPORT_REVOKED = 'group.support_revoked';

    public const string GROUP_SUPPORT_READ = 'group.support_read';

    public const string GROUP_RECRUITMENT_READ = 'group.recruitment_read';

    public const string APPROVAL_FLOW_SAVED = 'approval.flow_saved';

    public const string APPROVAL_FLOW_REMOVED = 'approval.flow_removed';

    public const string APPROVAL_SUBMITTED = 'approval.submitted';

    public const string APPROVAL_STEP_APPROVED = 'approval.step_approved';

    public const string APPROVAL_GRANTED = 'approval.granted';

    public const string APPROVAL_REJECTED = 'approval.rejected';

    public const string APPROVAL_RETURNED = 'approval.returned';

    public const string APPROVAL_CANCELLED = 'approval.cancelled';

    public const string APPROVAL_APPROVER_VACATED = 'approval.approver_vacated';

    public const string APPROVAL_APPROVER_REPLACED = 'approval.approver_replaced';

    public const string APPROVAL_DELEGATION_CREATED = 'approval.delegation_created';

    public const string APPROVAL_DELEGATION_REMOVED = 'approval.delegation_removed';

    public const string WORKFORCE_PLAN_CREATED = 'workforce.plan_created';

    public const string WORKFORCE_PLAN_UPDATED = 'workforce.plan_updated';

    public const string WORKFORCE_PLAN_SUBMITTED = 'workforce.plan_submitted';

    public const string WORKFORCE_PLAN_REVISED = 'workforce.plan_revised';

    public const string WORKFORCE_PLAN_APPROVED = 'workforce.plan_approved';

    public const string WORKFORCE_PLAN_REJECTED = 'workforce.plan_rejected';

    public const string WORKFORCE_PLAN_RETURNED = 'workforce.plan_returned';

    public const string WORKFORCE_PLAN_SUPERSEDED = 'workforce.plan_superseded';

    public const string GROUP_GRADE_CREATED = 'group.grade_created';

    public const string GROUP_GRADE_UPDATED = 'group.grade_updated';

    public const string GROUP_GRADE_DEACTIVATED = 'group.grade_deactivated';

    public const string GROUP_GRADE_REACTIVATED = 'group.grade_reactivated';

    public const string GROUP_UNIT_TYPE_CREATED = 'group.unit_type_created';

    public const string GROUP_UNIT_TYPE_UPDATED = 'group.unit_type_updated';

    public const string GROUP_UNIT_TYPE_DEACTIVATED = 'group.unit_type_deactivated';

    public const string GROUP_UNIT_TYPE_REACTIVATED = 'group.unit_type_reactivated';

    public const string GROUP_COST_CENTRE_CREATED = 'group.cost_centre_created';

    public const string GROUP_COST_CENTRE_UPDATED = 'group.cost_centre_updated';

    public const string GROUP_COST_CENTRE_DEACTIVATED = 'group.cost_centre_deactivated';

    public const string GROUP_COST_CENTRE_REACTIVATED = 'group.cost_centre_reactivated';

    public const string GROUP_UNIT_CREATED = 'group.unit_created';

    public const string GROUP_UNIT_RESTRUCTURED = 'group.unit_restructured';

    public const string GROUP_UNIT_DEACTIVATED = 'group.unit_deactivated';

    public const string GROUP_UNIT_REACTIVATED = 'group.unit_reactivated';

    public const string GROUP_POSITION_CREATED = 'group.position_created';

    public const string GROUP_POSITION_CHANGED = 'group.position_changed';

    public const string GROUP_POSITION_DEACTIVATED = 'group.position_deactivated';

    public const string GROUP_POSITION_REACTIVATED = 'group.position_reactivated';
}
