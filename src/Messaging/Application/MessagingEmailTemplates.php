<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Messaging context, registered in the shared catalog by its service provider. The text never carries the message itself. */
final class MessagingEmailTemplates
{
    public const string UNREAD = 'messaging.unread_message';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::UNREAD,
                'Sent once when a chat conversation still has an unread message 15 minutes after it arrived, until the conversation is read.',
                [
                    new EmailVariable('name', 'The recipient\'s name', 'Siti'),
                    new EmailVariable('company_name', 'The name of the company in the conversation', 'PT Contoh Maju'),
                ],
                TranslatedText::fromArray(['id' => 'Anda punya pesan baru dari {{company_name}}', 'en' => 'You have a new message about {{company_name}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nAda pesan yang belum Anda baca dalam percakapan dengan {{company_name}}.\n\nBuka akun Anda untuk membacanya.",
                    'en' => "Hello {{name}},\n\nThere is a message you have not read yet in your conversation with {{company_name}}.\n\nOpen your account to read it.",
                ]),
            ),
        ];
    }
}
