<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Invitations\IssueInvitation;
use Src\Company\Application\Invitations\IssueInvitationCommand;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\MemberPermissionRules;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A company admin invites a colleague, as company admin or as a member with the permissions checked for them. */
final readonly class InviteMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private IssueInvitation $issue,
    ) {}

    /**
     * @param  list<string>  $permissions  the permissions of a member (catalog names); ignored for a company admin
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `permissions-required` or `permission-requires-view` for a member
     * @throws StateConflict `email-already-registered`
     * @throws TooManyAttempts `invitation-cooldown`
     * @throws ExternalServiceUnavailable `invitation-link-unavailable`
     */
    public function execute(Actor $actor, string $name, string $email, CompanyRole $role, array $permissions, string $language, ?string $ipAddress, ?string $userAgent): Invitation
    {
        $company = $this->guard->company($actor);
        $checked = $role === CompanyRole::Member ? MemberPermissionRules::validated($permissions) : null;

        return $this->issue->execute(new IssueInvitationCommand(
            $company, $email, $role, trim($name), $checked, (int) $actor->id, null, $language, $ipAddress, $userAgent,
        ));
    }
}
