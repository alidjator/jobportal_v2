<?php

namespace Src\JobAlert\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the JobAlert context, registered in the shared catalog by its service provider. The body carries no link (no client page exists yet). */
final class JobAlertEmailTemplates
{
    public const string DIGEST = 'jobalert.digest';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::DIGEST,
                'Sent for a job alert: right away for an immediate alert (at most 5 a day per alert), or as the daily or weekly summary. Lists up to 10 new jobs.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('alert_name', 'The name the job seeker gave the alert', 'Backend jobs in Jakarta'),
                    new EmailVariable('job_count', 'How many new jobs match', '3'),
                    new EmailVariable('jobs', 'The titles of the newest matching jobs (up to 10), one per line', "Backend Developer\n\nData Engineer"),
                ],
                TranslatedText::fromArray(['id' => 'Lowongan baru untuk alert {{alert_name}}', 'en' => 'New jobs for your alert {{alert_name}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nLowongan baru yang cocok dengan alert Anda \"{{alert_name}}\" (total {{job_count}}):\n\n{{jobs}}\n\nBuka akun Anda untuk melihat semuanya dan mengatur alert Anda.",
                    'en' => "Hello {{name}},\n\nNew jobs that match your alert \"{{alert_name}}\" ({{job_count}} in total):\n\n{{jobs}}\n\nOpen your account to see them all and to manage your alerts.",
                ]),
            ),
        ];
    }
}
