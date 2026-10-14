<?php

namespace Src\Company\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Company context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class CompanyEmailTemplates
{
    public const string INVITATION = 'company.invitation';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::INVITATION,
                'Sent when someone is invited to join a company, with the link that accepts the invitation.',
                [
                    new EmailVariable('company', 'The name of the company that invites', 'PT Contoh Maju'),
                    new EmailVariable('role', 'The role offered, in the language of the email', 'Team member'),
                    new EmailVariable('link', 'The link that accepts the invitation', 'https://example.test/invitations/abc123', required: true),
                    new EmailVariable('days', 'How many days the invitation stays valid', '7'),
                ],
                TranslatedText::fromArray(['id' => 'Undangan bergabung dengan {{company}}', 'en' => 'You are invited to join {{company}}']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\n{{company}} mengundang Anda bergabung sebagai {{role}}.\n\nBuka tautan ini untuk menerima undangan dan membuat akun Anda:\n\n{{link}}\n\nUndangan berlaku selama {{days}} hari dan hanya dapat digunakan sekali.\n\nJika Anda tidak mengenal pengirimnya, abaikan email ini.",
                    'en' => "Hello,\n\n{{company}} invites you to join as {{role}}.\n\nOpen this link to accept the invitation and create your account:\n\n{{link}}\n\nThe invitation is valid for {{days}} days and can be used once.\n\nIf you do not know the sender, you can ignore this email.",
                ]),
            ),
        ];
    }
}
