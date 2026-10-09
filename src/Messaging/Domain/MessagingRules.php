<?php

namespace Src\Messaging\Domain;

/** The fixed limits of messaging. The limits an admin can change (file size, bulk recipients, daily cap) are settings. */
final class MessagingRules
{
    public const int BODY_MAX_LENGTH = 5000;

    public const int ATTACHMENTS_MAX = 5;

    public const int ATTACHMENT_NAME_MAX_LENGTH = 100;

    /** A sender may delete their own message for this many minutes after sending it. */
    public const int DELETE_WINDOW_MINUTES = 15;

    /** A message that is still unread after this many minutes is reminded by one email per conversation. */
    public const int EMAIL_DELAY_MINUTES = 15;
}
