<?php

namespace Src\Shared\Application\Organization;

/** Which group a legal entity belongs to and which entities a group holds, for the contexts that tie their records to a legal entity (Workforce). Provided by Organization. */
interface LegalEntityDirectory
{
    /** The group of the legal entity; null for an entity that does not exist. */
    public function groupOf(int $legalEntityId): ?int;

    /**
     * The legal name of every legal entity of the group, keyed by id and in name order.
     *
     * @return array<int, string>
     */
    public function namesOfGroup(int $groupId): array;

    /** Whether the legal entity exists and is active. */
    public function isActive(int $legalEntityId): bool;
}
