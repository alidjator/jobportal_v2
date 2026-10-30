<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the JobInvitation context, registered in the shared catalog by its service provider. The body of `invitation.received` carries no link; `invitation.email_received` carries the claim and refuse links. */
final class InvitationEmailTemplates
{
    public const string RECEIVED = 'invitation.received';

    public const string EMAIL_RECEIVED = 'invitation.email_received';

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
            new EmailTemplateDefinition(
                self::EMAIL_RECEIVED,
                'Sent to a person without an account when a company invites them by email to apply to a job (a walk-in, a referral, a CV received by email). It names how the company got in touch and carries the link to claim the invitation and the link to refuse it.',
                [
                    new EmailVariable('name', 'The recipient\'s name as the company typed it', 'Siti'),
                    new EmailVariable('company_name', 'The company that invites', 'PT Maju Jaya'),
                    new EmailVariable('job_title', 'The job the person is invited to', 'Backend Developer'),
                    new EmailVariable('message', 'The personal note of the company (empty when there is none)', 'It was a pleasure to meet you at the job fair.'),
                    new EmailVariable('channel', 'How the company got in touch with the person', 'a job fair'),
                    new EmailVariable('claim_link', 'The link to sign in or sign up and apply', 'https://karir.satu.solutions/undangan-lamaran/abc'),
                    new EmailVariable('decline_link', 'The link to refuse and have the data of the invitation deleted', 'https://karir.satu.solutions/undangan-lamaran/abc?tolak=1'),
                ],
                TranslatedText::fromArray(['id' => '{{company_name}} mengundang Anda melamar', 'en' => '{{company_name}} invites you to apply']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} menerima data Anda melalui {{channel}} dan mengundang Anda melamar lowongan \"{{job_title}}\".\n\n{{message}}\n\nMasuk atau daftar untuk melihat undangan dan melamar: {{claim_link}}\n\nJika CV Anda dikirim ke perusahaan, hanya tim perekrutan yang dapat melihatnya dan CV itu dihapus dalam 30 hari bila Anda tidak menanggapi. Bukan Anda, atau tidak berminat? Tolak dan hapus data Anda: {{decline_link}}",
                    'en' => "Hello {{name}},\n\n{{company_name}} received your details through {{channel}} and invites you to apply for the job \"{{job_title}}\".\n\n{{message}}\n\nSign in or sign up to read the invitation and to apply: {{claim_link}}\n\nIf your CV was sent to the company, only the hiring team can see it and it is deleted within 30 days when you do not respond. Not you, or not interested? Refuse and have your data deleted: {{decline_link}}",
                ]),
            ),
        ];
    }
}
