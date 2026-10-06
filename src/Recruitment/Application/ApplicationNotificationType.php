<?php

namespace Src\Recruitment\Application;

/** The `type` keys of the in-app notifications of this context. The server stores no text: clients write the message from the key and the data. */
final class ApplicationNotificationType
{
    public const string RECEIVED = 'application.received';

    /** The kind the applicant can tune (email delivery of stage changes, step 8): in-app notifications are always written. */
    public const string UPDATE_PREFERENCE = 'application_update';
}
