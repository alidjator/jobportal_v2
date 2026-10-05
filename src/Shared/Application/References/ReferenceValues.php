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

    /**
     * The name of the item in the language (the other language when that one is empty), whether or not the item is still active, so a
     * profile that refers to an item an admin later deactivated keeps showing its name. Null when there is no such item.
     */
    public function name(string $master, int $id, string $language): ?string;
}
