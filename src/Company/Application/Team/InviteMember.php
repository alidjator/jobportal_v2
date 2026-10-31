<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Invitations\IssueInvitation;
use Src\Company\Application\Invitations\IssueInvitationCommand;
use Src\Company\Domain\Invitation;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A company admin invites a colleague with a preset: the built-in company admin, or the permissions of a built-in or saved preset for a member. */
final readonly class InviteMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private IssueInvitation $issue,
        private InvitationEmailDomains $domains,
        private ResolveMemberPreset $presets,
    ) {}

    /**
     * @param  string  $preset  a built-in preset (`recruiter`) or a saved one (`saved_12`)
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `unknown-permission-preset` or `email-domain-not-allowed`
     * @throws StateConflict `email-already-registered`
     * @throws TooManyAttempts `invitation-cooldown`
     * @throws ExternalServiceUnavailable `invitation-link-unavailable`
     */
    public function execute(Actor $actor, string $name, string $email, string $preset, string $language, ?string $ipAddress, ?string $userAgent): Invitation
    {
        $company = $this->guard->company($actor);
        $this->domains->assertAllowed((int) $company->id, trim($email));
        $resolved = $this->presets->execute((int) $company->id, $preset);

        return $this->issue->execute(new IssueInvitationCommand(
            $company, $email, $resolved->role, trim($name), $resolved->permissionsOrNull(), (int) $actor->id, null, $language, $ipAddress, $userAgent,
        ));
    }
}
