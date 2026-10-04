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
}
