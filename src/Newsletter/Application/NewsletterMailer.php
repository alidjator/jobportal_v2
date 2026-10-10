<?php

namespace Src\Newsletter\Application;

/** Sends one issue to one address, now (the caller is a queue job). Throws when the mail cannot be sent, so the job retries. */
interface NewsletterMailer
{
    public function send(IssueMessage $message): void;
}
