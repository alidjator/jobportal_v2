<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Emails a password reset code to a public user who may reset (see `User::canResetPassword()`). The outcome is the same
 * whether or not the account exists, may reset or has used up its own request limit (60 seconds apart, 5 per hour):
 * nothing is revealed and no email is sent.
 * Only the per-address limit is visible to the caller.
 */
final readonly class RequestPasswordReset
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRequestThrottle $throttle,
        private VerificationCodeIssuer $codes,
        private VerificationCodeSender $sender,
        private TransactionManager $transactions,
    ) {}

    /** @throws TooManyAttempts when the client address has used up its requests */
    public function execute(string $email, ?string $ipAddress): void
    {
        $this->throttle->hit($ipAddress);

        $user = $this->users->findByEmail(mb_strtolower(trim($email)));

        if ($user === null || ! $user->canResetPassword()) {
            return;
        }

        try {
            $issued = $this->transactions->run(fn () => $this->codes->issueForUser((int) $user->id, VerificationPurpose::PasswordReset));
        } catch (TooManyAttempts) {
            return;
        }

        $this->sender->sendPasswordReset($user->email(), $user->name(), $user->locale(), $issued->code, $issued->expiresAt);
    }
}
