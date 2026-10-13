<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\AdminPasswordReset;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The operator sets a new password for an administrator who lost theirs (console only); every token is revoked. The second factor stays. */
final readonly class ResetAdminPassword
{
    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return int the administrator's id
     *
     * @throws EntityNotFound when no administrator has the email address
     */
    public function execute(string $email, string $password, string $operator): int
    {
        $admin = $this->admins->findByEmail(mb_strtolower(trim($email))) ?? throw EntityNotFound::of('Admin', $email);
        $adminId = (int) $admin->id;

        $admin->changePassword($this->hasher->make($password));

        $this->transactions->run(function () use ($admin, $adminId): void {
            $this->admins->save($admin);
            $this->tokens->revokeAll(Actor::admin($adminId));
        });

        $this->events->dispatch([new AdminPasswordReset($adminId, $operator, $this->clock->now())]);

        return $adminId;
    }
}
