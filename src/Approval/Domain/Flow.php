<?php

namespace Src\Approval\Domain;

use DateTimeImmutable;

/** The ordered steps that a subject of one type goes through for an owner (a group, or a tenant that replaces the group's flow). `updatedAt` and `updatedByUserId` say when and by whom it was last saved; null for a flow not stored yet (and, for the account, for a flow saved before it was recorded). */
final readonly class Flow
{
    /** @param  list<FlowStep>  $steps */
    public function __construct(public ?int $id, public FlowOwner $owner, public string $subjectType, public array $steps, public ?DateTimeImmutable $updatedAt = null, public ?int $updatedByUserId = null) {}
}
