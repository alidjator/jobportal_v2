<?php

namespace Src\Dashboard\Application;

/** Emails the daily digest to a team member, in their own language. */
interface TeamDigestSender
{
    /** @param  array<string, string>  $variables */
    public function send(string $email, string $language, array $variables): void;
}
