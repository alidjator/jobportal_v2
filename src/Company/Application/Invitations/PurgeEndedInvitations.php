<?php

namespace Src\Company\Application\Invitations;

use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Clock;

/** Deletes the invitations that expired, were revoked or were accepted more than 30 days ago (nightly): they hold an email address. */
final readonly class PurgeEndedInvitations
{
    public function __construct(private InvitationRepository $invitations, private Clock $clock) {}

    public function execute(): int
    {
        return $this->invitations->purgeEnded($this->clock->now());
    }
}
