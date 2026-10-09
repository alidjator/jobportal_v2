<?php

namespace Src\JobInvitation\Application;

/**
 * The `type` keys of the in-app notifications of this context (the server stores no text): `invitation.received` for the candidate (`invitation_id`, `job_id`,
 * `company_id`) and `invitation.declined` for the member who invited (`invitation_id`, `job_id`, `reason`); and the preference that tunes the candidate's email.
 */
final class InvitationNotificationType
{
    public const string RECEIVED = 'invitation.received';

    public const string DECLINED = 'invitation.declined';

    public const string EMAIL_PREFERENCE = 'invitation';
}
