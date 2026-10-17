<?php

namespace Src\Assessment\Application\Attempts;

/** Emails an assessor about a submitted test, in their own language: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface TeamSubmissionSender
{
    /** @param  array<string, string>  $variables  the template variables, the recipient's `name` included */
    public function send(string $email, string $locale, string $templateKey, array $variables): void;
}
