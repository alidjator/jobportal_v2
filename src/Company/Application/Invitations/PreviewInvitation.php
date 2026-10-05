<?php

namespace Src\Company\Application\Invitations;

use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Public: shows the company, the invited email and the role, so the accept form can say what the invitee is joining. */
final readonly class PreviewInvitation
{
    public function __construct(
        private PendingInvitationFinder $finder,
        private InvitationThrottle $throttle,
    ) {}

    /**
     * @throws TooManyAttempts when the client address has used up its attempts
     * @throws EntityNotFound `invitation-not-found` for any token that is not a pending invitation
     * @throws AccessDenied `company-suspended`
     */
    public function execute(string $plainToken, ?string $ipAddress): InvitationPreview
    {
        $this->throttle->hit($ipAddress);

        [$invitation, $company] = $this->finder->find($plainToken);

        return new InvitationPreview($company->name(), $invitation->email, $invitation->role, $invitation->expiresAt);
    }
}
