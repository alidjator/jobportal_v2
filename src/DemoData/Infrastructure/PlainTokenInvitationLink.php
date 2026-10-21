<?php

namespace Src\DemoData\Infrastructure;

use Src\Company\Application\Invitations\InvitationLinkBuilder;

/** The invitation "link" of a demo run is the plain token, which {@see InMemoryDemoInbox} keeps; no client URL needs to be configured. */
final readonly class PlainTokenInvitationLink implements InvitationLinkBuilder
{
    public function build(string $plainToken): string
    {
        return $plainToken;
    }

    public function assertAvailable(): void {}
}
