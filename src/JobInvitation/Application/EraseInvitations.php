<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Accounts\ErasureStep;

/** Deletes the invitations of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseInvitations implements ErasureStep
{
    public function __construct(private InvitationRepository $invitations) {}

    public function name(): string
    {
        return 'invitations';
    }

    public function execute(int $userId): void
    {
        $this->invitations->deleteOfUser($userId);
    }
}
