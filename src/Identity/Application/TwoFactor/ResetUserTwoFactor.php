<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\Events\UserTwoFactorReset;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The operator's answer to a lost device of a company member or a psychologist whose own company admin cannot help (a lone company admin; run from the
 * console): removes the second factor, the recovery codes and the open challenges and signs the user out everywhere. They enrol again at the next sign-in.
 */
final readonly class ResetUserTwoFactor
{
    public function __construct(
        private UserRepository $users,
        private MemberTwoFactor $factors,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound when no user has the email address, or the account is a job seeker's (it has no second factor) */
    public function execute(string $email, string $operator): int
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($email)));

        if ($user === null || ! ($user->isCompanyMember() || $user->isPsychologist())) {
            throw EntityNotFound::of('User with a second factor', $email);
        }

        $userId = (int) $user->id;

        $this->transactions->run(fn () => $this->factors->reset($userId));
        $this->events->dispatch([new UserTwoFactorReset($userId, $operator, $this->clock->now())]);

        return $userId;
    }
}
