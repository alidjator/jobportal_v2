<?php

namespace Src\Approval\Domain;

/** The ordered steps that a subject of one type goes through for an owner (a group, or a tenant that replaces the group's flow). */
final readonly class Flow
{
    /** @param  list<FlowStep>  $steps */
    public function __construct(public ?int $id, public FlowOwner $owner, public string $subjectType, public array $steps) {}
}
