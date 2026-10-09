<?php

namespace Src\JobAlert\Application;

/**
 * The `type` key of the in-app notification of this context (its data holds `alert_id`, `job_ids` with the newest 10 jobs and `total`; the server stores no
 * text), and the preference that tunes the email (in-app notifications are always written).
 */
final class JobAlertNotificationType
{
    public const string MATCHES = 'job_alert.matches';

    public const string EMAIL_PREFERENCE = 'job_alert';
}
