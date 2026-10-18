<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `users.view` opens one account. Each opening is audited (`user.viewed`, the account id only). An anonymised account is gone for the admin too. */
final readonly class GetAdminUser
{
    public function __construct(
        private UserRepository $users,
        private EmailSuppressions $suppressions,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the account does not exist or was anonymised
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): AdminUserView
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can open an account.');
        }

        $user = $this->users->findById($userId);

        if ($user === null || $user->anonymizedAt() !== null) {
            throw EntityNotFound::of('User', $userId);
        }

        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, (int) $actor->id, AuditAction::USER_VIEWED, 'user', $userId, [], $ipAddress, $userAgent, $this->clock->now(),
        ));

        return new AdminUserView($user, $this->suppressions->reasons([$user->email()])[mb_strtolower($user->email())] ?? null);
    }
}
