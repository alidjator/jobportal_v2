<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\EmailVerified;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class VerifyEmail
{
    public function __construct(
        private UserRepository $users,
        private VerificationCodeRepository $codes,
        private VerificationCodeChecker $checker,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict when the email is already verified
     * @throws BusinessRuleViolation when the code is wrong (`invalid-verification-code`) or no usable code exists (`verification-code-expired`)
     */
    public function execute(Actor $actor, string $code): void
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);

        if ($user->isEmailVerified()) {
            throw new StateConflict('The email address is already verified.', 'email-already-verified');
        }

        $now = $this->clock->now();
        $stored = $this->checker->check(AccountScope::User, (int) $user->id, VerificationPurpose::EmailVerification, $code, $now);

        $this->transactions->run(function () use ($stored, $user, $now): void {
            $stored->consume($now);
            $this->codes->save($stored);

            $user->verifyEmail($now);
            $this->users->save($user);
        });

        $this->events->dispatch([new EmailVerified((int) $user->id, $now)]);
    }
}
