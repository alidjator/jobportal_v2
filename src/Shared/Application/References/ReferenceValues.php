<?php

namespace Src\Shared\Application\References;

/** Lets other contexts check a reference to a master (industry, organization type, ...) without reading ReferenceData's tables. Provided by ReferenceData. */
interface ReferenceValues
{
    /**
     * True when the master has an active item with that id. `$master` is the URL name of the master, for example `industries`.
     * An unknown master is a programming error and throws.
     */
    public function isActive(string $master, int $id): bool;
}
