<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Events\CompanyInvitationUpdated;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\MemberPermissionRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A company admin edits a pending invitation: the name, the role and the permissions, by the same rules as inviting. The link stays the same and the
 * email cannot change (revoke and invite again).
 */
final readonly class UpdateInvitation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private InvitationRepository $invitations,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  ?list<string>  $permissions  null leaves them as they are
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the company has no pending invitation with that id
     * @throws BusinessRuleViolation `permissions-required` or `permission-requires-view`
     */
    public function execute(Actor $actor, int $invitationId, ?string $name, ?CompanyRole $role, ?array $permissions, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($actor, $company, $invitationId, $name, $role, $permissions, $ipAddress, $userAgent, $now): ?CompanyInvitationUpdated {
            $invitation = $this->invitations->findOfCompany((int) $company->id, $invitationId, forUpdate: true);

            if ($invitation === null || ! $invitation->isPending($now)) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $newRole = $role ?? $invitation->role;
            $newName = $name === null ? $invitation->name : trim($name);
            $newPermissions = $newRole === CompanyRole::CompanyAdmin
                ? null
                : MemberPermissionRules::validated($permissions ?? $invitation->permissions ?? []);

            if ($newRole === $invitation->role && $newName === $invitation->name && $newPermissions === $this->ordered($invitation->permissions)) {
                return null;
            }

            $this->invitations->save($invitation->edited($newRole, $newName, $newPermissions));

            return new CompanyInvitationUpdated(
                (int) $company->id, $invitationId, $invitation->role->value, $newRole->value, $newPermissions, (int) $actor->id, $ipAddress, $userAgent, $now,
            );
        });

        if ($event !== null) {
            $this->events->dispatch([$event]);
        }
    }

    /**
     * @param  ?list<string>  $permissions
     * @return ?list<string> in catalog order, so two sets compare equal whatever order they were stored in
     */
    private function ordered(?array $permissions): ?array
    {
        return $permissions === null ? null : array_values(array_filter(
            CompanyPermission::values(),
            static fn (string $name): bool => in_array($name, $permissions, true),
        ));
    }
}
