<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the JobInvitation context, registered in the shared catalog by its service provider. The body carries no link (no client page exists yet). */
final class InvitationEmailTemplates
{
    public const string RECEIVED = 'invitation.received';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::RECEIVED,
                'Sent to a job seeker when a company invites them to apply to a job. Carries the personal note of the company when there is one.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('company_name', 'The company that invites', 'PT Maju Jaya'),
                    new EmailVariable('job_title', 'The job the candidate is invited to', 'Backend Developer'),
                    new EmailVariable('message', 'The personal note of the company (empty when there is none)', 'We liked your profile and think you would fit the team.'),
                ],
                TranslatedText::fromArray(['id' => '{{company_name}} mengundang Anda melamar', 'en' => '{{company_name}} invites you to apply']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} mengundang Anda untuk melamar lowongan \"{{job_title}}\".\n\n{{message}}\n\nBuka akun Anda untuk membaca undangan dan melamar.",
                    'en' => "Hello {{name}},\n\n{{company_name}} invites you to apply for the job \"{{job_title}}\".\n\n{{message}}\n\nOpen your account to read the invitation and to apply.",
                ]),
            ),
        ];
    }
}
