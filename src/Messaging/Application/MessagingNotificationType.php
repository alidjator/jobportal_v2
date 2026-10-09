<?php

namespace Src\Messaging\Application;

/** The `type` keys of the in-app notifications of this context, and the preference that tunes the reminder email. The server stores no text: clients write the message. */
final class MessagingNotificationType
{
    public const string MESSAGE_RECEIVED = 'message.received';

    /** The kind the user can tune: email delivery of the reminder for an unread conversation (in-app notifications are always written). */
    public const string UNREAD_PREFERENCE = 'chat_message';
}
