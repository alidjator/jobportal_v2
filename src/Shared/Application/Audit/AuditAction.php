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

    public const string ADMIN_ROLE_CREATED = 'admin.role_created';

    public const string ADMIN_ROLE_PERMISSIONS_CHANGED = 'admin.role_permissions_changed';

    public const string ADMIN_ROLE_DELETED = 'admin.role_deleted';

    public const string ADMIN_ROLE_CHANGED = 'admin.role_changed';

    public const string ADMIN_SUSPENDED = 'admin.suspended';

    public const string ADMIN_REACTIVATED = 'admin.reactivated';

    public const string ADMIN_PASSWORD_RESET = 'admin.password_reset';

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

    public const string COMPANY_DOCUMENT_SUBMITTED = 'company.document_submitted';

    public const string COMPANY_DOCUMENT_DELETED = 'company.document_deleted';

    public const string JOB_ASSIGNEES_CHANGED = 'job.assignees_changed';

    public const string JOB_PUBLISHED = 'job.published';

    public const string JOB_CLOSED = 'job.closed';

    public const string JOB_REOPENED = 'job.reopened';

    public const string JOB_SENT_FOR_REVIEW = 'job.sent_for_review';

    public const string JOB_EXPIRED = 'job.expired';

    public const string JOB_APPROVED = 'job.approved';

    public const string JOB_REJECTED = 'job.rejected';

    public const string EXPORT_APPLICANTS = 'export.applicants';

    public const string JOB_TAKEN_DOWN = 'job.taken_down';

    public const string JOB_RESTORED = 'job.restored';

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
}
