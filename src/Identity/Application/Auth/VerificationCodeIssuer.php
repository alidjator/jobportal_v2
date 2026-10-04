<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\VerificationCode;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Issues verification codes for a user and enforces the request limits per purpose: at least 60 seconds between codes
 * and at most 5 codes per hour. A new code replaces every earlier one of the same purpose. Call it inside the use case's
 * transaction and send the code only after the transaction commits.
 */
final readonly class VerificationCodeIssuer
{
    public const int COOLDOWN_SECONDS = 60;

    public const int MAX_PER_HOUR = 5;

    public function __construct(
        private VerificationCodeRepository $codes,
        private VerificationCodeHasher $hasher,
        private VerificationCodeGenerator $generator,
        private Clock $clock,
    ) {}

    /** @throws TooManyAttempts when the request limits are exceeded */
    public function issueForUser(int $userId, VerificationPurpose $purpose): IssuedVerificationCode
    {
        $now = $this->clock->now();
        $scope = AccountScope::User;

        $last = $this->codes->lastIssuedAt($scope, $userId, $purpose);

        if ($last !== null) {
            $wait = $last->getTimestamp() + self::COOLDOWN_SECONDS - $now->getTimestamp();

            if ($wait > 0) {
                throw new TooManyAttempts('Wait a moment before requesting another code.', $wait, 'verification-code-cooldown');
            }
        }

        $issuedInLastHour = $this->codes->issuedSince($scope, $userId, $purpose, $now->modify('-1 hour'));

        if (count($issuedInLastHour) >= self::MAX_PER_HOUR) {
            $wait = $issuedInLastHour[0]->getTimestamp() + 3600 - $now->getTimestamp();

            throw new TooManyAttempts('Too many codes were requested. Try again later.', max($wait, 1), 'verification-code-limit-reached');
        }

        $this->codes->consumeAllUnconsumed($scope, $userId, $purpose, $now);

        $plain = $this->generator->generate();
        $stored = $this->codes->save(VerificationCode::issue($scope, $userId, $purpose, $this->hasher->make($plain), $now));

        return new IssuedVerificationCode($plain, $stored->expiresAt);
    }
}
