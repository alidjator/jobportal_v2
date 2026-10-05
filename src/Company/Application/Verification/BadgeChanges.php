<?php

namespace Src\Company\Application\Verification;

use DateTimeImmutable;
use Src\Company\Domain\Events\CompanyVerificationGranted;
use Src\Company\Domain\Events\CompanyVerificationWithdrawn;
use Src\Shared\Domain\DomainEvent;

/** The companies whose verification badge a recalculation granted or withdrew. A company that kept its state is in neither list. */
final readonly class BadgeChanges
{
    /**
     * @param  list<int>  $granted
     * @param  list<int>  $withdrawn
     */
    public function __construct(
        public array $granted,
        public array $withdrawn,
    ) {}

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * One event per company, to dispatch after the transaction has committed.
     *
     * @return list<DomainEvent>
     */
    public function granted(DateTimeImmutable $at): array
    {
        return array_map(static fn (int $companyId): DomainEvent => new CompanyVerificationGranted($companyId, $at), $this->granted);
    }

    /** @return list<DomainEvent> */
    public function withdrawn(DateTimeImmutable $at): array
    {
        return array_map(static fn (int $companyId): DomainEvent => new CompanyVerificationWithdrawn($companyId, $at), $this->withdrawn);
    }
}
