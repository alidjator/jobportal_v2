<?php

namespace Src\JobInvitation\Application;

/** The CV of an invitation by email, ready to be sent: its bytes and its name. */
final readonly class EmailInvitationFile
{
    public function __construct(public string $contents, public string $name) {}
}
