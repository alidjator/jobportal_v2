<?php

namespace Src\DemoData\Application;

/**
 * What the demo run would have emailed: the invitation tokens and the email verification codes, kept in memory so the run can accept the invitations and
 * verify the emails itself. Nothing is sent and nothing is stored.
 */
interface DemoInbox
{
    /** The plain token of the latest invitation sent to the email. */
    public function invitationToken(string $email): string;

    /** The latest email verification code sent to the email. */
    public function verificationCode(string $email): string;
}
