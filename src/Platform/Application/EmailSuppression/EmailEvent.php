<?php

namespace Src\Platform\Application\EmailSuppression;

/** One delivery event of an email provider that should stop mail to the address: a hard bounce or a complaint. Soft bounces never become events. */
final readonly class EmailEvent
{
    public function __construct(
        public string $email,
        public EmailSuppressionReason $reason,
        public ?string $details = null,
    ) {}
}
