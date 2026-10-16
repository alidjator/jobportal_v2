<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Today, the day a read looks at and the rule for the day a change takes effect: today or later freely, in the past only with a reason of 5 to 500 characters. */
final readonly class ChangeDating
{
    public function __construct(private Clock $clock) {}

    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->format('Y-m-d'));
    }

    public function asOf(?DateTimeImmutable $day): DateTimeImmutable
    {
        return $day ?? $this->today();
    }

    /** @throws BusinessRuleViolation `reason-required` */
    public function resolve(?DateTimeImmutable $validFrom, ?string $reason): Dated
    {
        $from = $validFrom ?? $this->today();
        $reason = $reason === null ? null : trim($reason);

        if ($from < $this->today() && ($reason === null || mb_strlen($reason) < 5)) {
            throw new BusinessRuleViolation('A change dated in the past needs a reason of 5 to 500 characters.', 'reason-required');
        }

        return new Dated($from, $reason === '' ? null : $reason);
    }
}
