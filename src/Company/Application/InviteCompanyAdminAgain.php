<?php

namespace Src\Company\Application;

use Src\Company\Application\Invitations\IssueInvitation;
use Src\Company\Application\Invitations\IssueInvitationCommand;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A platform admin invites a company admin to a company, for example when the first invitation expired. The invitee sets their own password. */
final readonly class InviteCompanyAdminAgain
{
    public function __construct(
        private CompanyRepository $companies,
        private IssueInvitation $issue,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company does not exist
     * @throws StateConflict `email-already-registered`
     * @throws TooManyAttempts `invitation-cooldown`
     * @throws ExternalServiceUnavailable `invitation-link-unavailable`
     */
    public function execute(Actor $actor, int $companyId, string $email, string $language, ?string $ipAddress, ?string $userAgent): Invitation
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can invite a company admin from outside the company.');
        }

        $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

        return $this->issue->execute(new IssueInvitationCommand(
            $company, $email, CompanyRole::CompanyAdmin, null, null, null, (int) $actor->id, $language, $ipAddress, $userAgent,
        ));
    }
}
