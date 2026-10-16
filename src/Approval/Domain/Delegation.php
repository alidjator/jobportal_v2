<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;

/** An approver lets another account decide in their place from one day to another (both included). */
final readonly class Delegation
{
    public function __construct(
        public ?int $id,
        public int $approverUserId,
        public int $delegateUserId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public DateTimeImmutable $createdAt,
    ) {}

    public function coversDay(DateTimeImmutable $day): bool
    {
        return $this->from <= $day && $this->to >= $day;
    }
}
