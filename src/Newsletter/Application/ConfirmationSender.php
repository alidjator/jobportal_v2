<?php

namespace Src\Newsletter\Application;

/** Sends the email that asks a person to confirm the address (queued). */
interface ConfirmationSender
{
    public function send(string $email, string $locale, string $link): void;
}
