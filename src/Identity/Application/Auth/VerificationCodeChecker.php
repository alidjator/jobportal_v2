<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\VerificationCode;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Checks a submitted code against the newest usable code of a user and purpose, counting wrong attempts. */
final readonly class VerificationCodeChecker
{
    public function __construct(
        private VerificationCodeRepository $codes,
        private VerificationCodeHasher $hasher,
    ) {}

    /**
     * Returns the matching code; the caller consumes it inside its own transaction.
     *
     * @throws BusinessRuleViolation `invalid-verification-code` when the code is wrong, `verification-code-expired` when no usable code exists
     */
    public function check(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, string $code, DateTimeImmutable $now): VerificationCode
    {
        $stored = $this->codes->findUsable($subjectType, $subjectId, $purpose, $now)
            ?? throw new BusinessRuleViolation('The verification code has expired or is no longer valid. Request a new one.', 'verification-code-expired');

        if (! $this->hasher->matches(trim($code), $stored->codeHash)) {
            // Saved outside any transaction that rolls back, so the failed attempt always counts.
            $stored->recordFailedAttempt();
            $this->codes->save($stored);

            throw new BusinessRuleViolation('The verification code is incorrect.', 'invalid-verification-code');
        }

        return $stored;
    }
}
