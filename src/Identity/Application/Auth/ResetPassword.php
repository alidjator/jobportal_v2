<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Sets a new password with the code that was emailed. It also verifies the email address (the code proves access to the
 * inbox) and revokes every token of the account, so every device must sign in again. It does not sign the user in.
 */
final readonly class ResetPassword
{
    public function __construct(
        private UserRepository $users,
        private VerificationCodeRepository $codes,
        private VerificationCodeChecker $checker,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-verification-code` when the code is wrong; `verification-code-expired` when no usable code exists,
     *                               which also covers an unknown or inactive account so the response does not reveal them
     */
    public function execute(string $email, string $code, string $newPassword, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($email)));

        if ($user === null || ! $user->canResetPassword()) {
            throw new BusinessRuleViolation('The verification code has expired or is no longer valid. Request a new one.', 'verification-code-expired');
        }

        $now = $this->clock->now();
        $stored = $this->checker->check(AccountScope::User, (int) $user->id, VerificationPurpose::PasswordReset, $code, $now);

        $this->transactions->run(function () use ($stored, $user, $newPassword, $now): void {
            $stored->consume($now);
            $this->codes->save($stored);

            $user->changePassword($this->hasher->make($newPassword));
            $user->verifyEmail($now);
            $this->users->save($user);

            $this->tokens->revokeAllForUser((int) $user->id);
        });

        $this->events->dispatch([new PasswordReset((int) $user->id, $ipAddress, $userAgent, $now)]);
    }
}
