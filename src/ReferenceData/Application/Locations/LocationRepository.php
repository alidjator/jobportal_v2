<?php

namespace Src\ReferenceData\Application\Locations;

interface LocationRepository
{
    /**
     * The areas of the level, ordered by name (postal codes by code); for a level with a parent column only those of that parent.
     *
     * @return list<LocationItem>
     */
    public function list(LocationLevel $level, ?int $parentId): array;
}
