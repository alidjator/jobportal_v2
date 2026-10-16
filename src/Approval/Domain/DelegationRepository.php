<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;

interface DelegationRepository
{
    public function find(int $id): ?Delegation;

    public function create(Delegation $delegation): Delegation;

    public function delete(int $id): void;

    /** @return list<Delegation> the delegations the approver gave that end today or later, by start day */
    public function ofApprover(int $approverUserId, DateTimeImmutable $today): array;

    /** @return list<int> the approvers who let this account decide for them on that day */
    public function delegatorsOf(int $delegateUserId, DateTimeImmutable $day): array;
}
