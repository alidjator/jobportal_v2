<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;

/** Deletes the invitations of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseInvitations
{
    public function __construct(private InvitationRepository $invitations) {}

    public function execute(int $userId): void
    {
        $this->invitations->deleteOfUser($userId);
    }
}
