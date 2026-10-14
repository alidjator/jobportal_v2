<?php

namespace Src\Company\Application;

/** The notification types of the Company context. Clients write and translate the message; the server stores only the type and its data. */
final class CompanyNotificationType
{
    public const string DOCUMENT_APPROVED = 'company.document_approved';

    public const string DOCUMENT_REJECTED = 'company.document_rejected';

    public const string VERIFIED = 'company.verified';

    public const string VERIFICATION_WITHDRAWN = 'company.verification_withdrawn';

    public const string PERMISSIONS_CHANGED = 'company.permissions_changed';

    public const string MEMBER_RENAMED = 'company.member_renamed';
}
