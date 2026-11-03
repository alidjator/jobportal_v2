<?php

namespace Src\Assessment\Application\Notices;

/** Emails a psychologist, in their own language: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface ProviderNoticeSender
{
    /** @param  array<string, string>  $variables  the template variables, the recipient's `name` included */
    public function send(string $email, string $locale, string $templateKey, array $variables): void;
}
