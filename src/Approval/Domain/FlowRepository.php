<?php

namespace Src\Approval\Domain;

interface FlowRepository
{
    public function find(FlowOwner $owner, string $subjectType): ?Flow;

    /** @return list<Flow> by subject type */
    public function ofOwner(FlowOwner $owner): array;

    /** Stores the flow, replacing the one the owner has for that subject type (steps and all). */
    public function save(Flow $flow): Flow;

    /** Whether the owner had a flow for the subject type. */
    public function remove(FlowOwner $owner, string $subjectType): bool;
}
