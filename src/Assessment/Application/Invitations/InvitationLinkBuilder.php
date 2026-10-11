<?php

namespace Src\Assessment\Application\Invitations;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

interface InvitationLinkBuilder
{
    /** The link the invitee opens: the client URL template with `{token}` replaced. */
    public function build(string $plainToken): string;

    /**
     * Fails early, before anything is stored or sent, when no link can be built.
     *
     * @throws ExternalServiceUnavailable `invitation-link-unavailable` when the client URL template is not configured
     */
    public function assertAvailable(): void;
}
