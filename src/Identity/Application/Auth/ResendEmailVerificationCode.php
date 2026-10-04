<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

final readonly class ResendEmailVerificationCode
{
    public function __construct(
        private UserRepository $users,
        private VerificationCodeIssuer $codes,
        private VerificationCodeSender $sender,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict when the email is already verified
     * @throws TooManyAttempts when the request limits are exceeded
     */
    public function execute(Actor $actor): void
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);

        if ($user->isEmailVerified()) {
            throw new StateConflict('The email address is already verified.', 'email-already-verified');
        }

        $issued = $this->transactions->run(fn () => $this->codes->issueForUser((int) $user->id, VerificationPurpose::EmailVerification));

        $this->sender->sendEmailVerification($user->email(), $user->name(), $user->locale(), $issued->code, $issued->expiresAt);
    }
}
