<?php

namespace Src\Shared\Application\Organization;

use DateTimeImmutable;

/** The positions of a legal entity for the contexts that plan or fill them (Workforce), without reading Organization's tables. Provided by Organization. */
interface PositionDirectory
{
    /**
     * The positions of the entity that exist on the day (a position whose first version starts later is left out), keyed by id and in code order.
     *
     * @return array<int, PositionSummary>
     */
    public function ofEntity(int $legalEntityId, DateTimeImmutable $on): array;
}
