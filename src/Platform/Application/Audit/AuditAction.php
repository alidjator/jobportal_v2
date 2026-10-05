<?php

namespace Src\Platform\Application\Audit;

/** The audit action names recorded so far. Names are `area.event` in lower snake case. */
final class AuditAction
{
    public const string LOGIN = 'auth.login';

    public const string LOGIN_FAILED = 'auth.login_failed';

    public const string PASSWORD_RESET = 'auth.password_reset';

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

    public const string COMPANY_DOCUMENT_SUBMITTED = 'company.document_submitted';

    public const string COMPANY_DOCUMENT_DELETED = 'company.document_deleted';

    public const string COMPANY_DOCUMENT_APPROVED = 'company.document_approved';

    public const string COMPANY_DOCUMENT_REJECTED = 'company.document_rejected';

    public const string COMPANY_DOCUMENT_VIEWED = 'company.document_viewed';

    public const string COMPANY_DOCUMENT_TYPE_CREATED = 'company_document_type.created';

    public const string COMPANY_DOCUMENT_TYPE_UPDATED = 'company_document_type.updated';
}
