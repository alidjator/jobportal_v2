<?php

namespace Src\Assessment\Application\Invitations;

/** The result of an accepted invitation. No token is issued: the psychologist signs in next, and enrols the second factor at that first sign-in. */
final readonly class AcceptedInvitation
{
    public function __construct(public string $email, public string $providerName) {}
}
