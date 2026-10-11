<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;

/** A test that was just sent, with the event to dispatch after the commit and whether it replaced an active invitation. */
final readonly class PlacedInvitation
{
    public function __construct(public Invitation $invitation, public InvitationChanged $event, public bool $replaced) {}
}
