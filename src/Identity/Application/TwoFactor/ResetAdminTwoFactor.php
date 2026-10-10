<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\TwoFactorReset;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The operator's answer to a lost device (run from the console): removes the second factor, the recovery codes and the open
 * challenges of an administrator and signs them out everywhere. They enrol again at the next sign-in.
 */
final readonly class ResetAdminTwoFactor
{
    public function __construct(
        private AdminRepository $admins,
        private RecoveryCodes $recoveryCodes,
        private TwoFactorChallenges $challenges,
        private AccessTokenIssuer $tokens,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound when no administrator has the email address */
    public function execute(string $email): int
    {
        $admin = $this->admins->findByEmail(mb_strtolower(trim($email))) ?? throw EntityNotFound::of('Admin', $email);
        $adminId = (int) $admin->id;

        $admin->resetTwoFactor();

        $this->transactions->run(function () use ($admin, $adminId): void {
            $this->admins->save($admin);
            $this->recoveryCodes->deleteForAdmin($adminId);
            $this->challenges->deleteForAdmin($adminId);
            $this->tokens->revokeAll(Actor::admin($adminId));
        });

        $this->events->dispatch([new TwoFactorReset($adminId, $this->clock->now())]);

        return $adminId;
    }
}
