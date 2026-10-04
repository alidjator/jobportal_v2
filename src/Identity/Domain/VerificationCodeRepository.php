<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

interface VerificationCodeRepository
{
    /** The newest code of the subject and purpose that is still usable at `$now`, or null. */
    public function findUsable(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $now): ?VerificationCode;

    public function save(VerificationCode $code): VerificationCode;

    /** Consumes every unconsumed code of the subject and purpose, so only a newly issued code can be used. */
    public function consumeAllUnconsumed(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $now): void;

    public function lastIssuedAt(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose): ?DateTimeImmutable;

    /**
     * Issue times of the subject's codes of this purpose since `$since`, oldest first.
     *
     * @return list<DateTimeImmutable>
     */
    public function issuedSince(AccountScope $subjectType, int $subjectId, VerificationPurpose $purpose, DateTimeImmutable $since): array;
}
