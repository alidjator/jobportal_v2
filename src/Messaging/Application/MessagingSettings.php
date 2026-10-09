<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Messaging context, registered in the shared catalog by its service provider. */
final class MessagingSettings
{
    public const string MAX_ATTACHMENT_BYTES = 'messaging.max_attachment_bytes';

    public const string MAX_BULK_RECIPIENTS = 'messaging.max_bulk_recipients';

    public const string MAX_DAILY_COMPANY_MESSAGES = 'messaging.max_daily_company_messages';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_ATTACHMENT_BYTES, 5 * 1024 * 1024, 100 * 1024, 25 * 1024 * 1024,
                'The largest attachment of a chat message, in bytes (PDF, JPG or PNG; at most 5 files per message).',
            ),
            SettingDefinition::integer(
                self::MAX_BULK_RECIPIENTS, 50, 1, 200,
                'The most applicants one bulk chat message may be sent to.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_DAILY_COMPANY_MESSAGES, 500, 1, 10000,
                'The most chat messages the members of one company may send per calendar day (Jakarta time), bulk messages counted per recipient.',
                overridable: true,
            ),
        ];
    }
}
