<?php

namespace Src\Newsletter\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Newsletter context, registered in the shared catalog by its service provider. The issues themselves are written by admins, not templates. */
final class NewsletterEmailTemplates
{
    public const string CONFIRM = 'newsletter.confirm';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::CONFIRM,
                'Sent when someone asks to join the newsletter, to confirm the address. The link is valid for 3 days.',
                [
                    new EmailVariable('link', 'The confirmation link', 'https://example.com/newsletter/confirm?token=abc', required: true),
                ],
                TranslatedText::fromArray(['id' => 'Konfirmasi langganan newsletter Anda', 'en' => 'Confirm your newsletter subscription']),
                TranslatedText::fromArray([
                    'id' => "Halo,\n\nKami menerima permintaan untuk mengirim newsletter ke alamat email ini. Buka tautan berikut untuk mengonfirmasi (berlaku 3 hari):\n\n{{link}}\n\nJika bukan Anda yang meminta, abaikan saja email ini dan Anda tidak akan menerima apa pun.",
                    'en' => "Hello,\n\nWe received a request to send the newsletter to this email address. Open the link below to confirm (valid for 3 days):\n\n{{link}}\n\nIf you did not ask for this, ignore this email and you will not receive anything.",
                ]),
            ),
        ];
    }
}
