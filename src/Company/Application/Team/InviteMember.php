<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Invitations\IssueInvitation;
use Src\Company\Application\Invitations\IssueInvitationCommand;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A company admin invites a colleague, as company admin or recruiter. */
final readonly class InviteMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private IssueInvitation $issue,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws StateConflict `email-already-registered`
     * @throws TooManyAttempts `invitation-cooldown`
     * @throws ExternalServiceUnavailable `invitation-link-unavailable`
     */
    public function execute(Actor $actor, string $email, CompanyRole $role, string $language, ?string $ipAddress, ?string $userAgent): Invitation
    {
        $company = $this->guard->company($actor);

        return $this->issue->execute(new IssueInvitationCommand($company, $email, $role, (int) $actor->id, null, $language, $ipAddress, $userAgent));
    }
}
