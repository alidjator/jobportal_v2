<?php

namespace Src\Approval\Application;

/** Emails a person about an approval, in their own language. */
interface ApprovalEmailSender
{
    /** @param  array<string, string>  $variables */
    public function send(string $email, string $language, string $template, array $variables): void;
}
