<?php

namespace Src\Shared\Application\Legal;

/**
 * The versions of the legal documents that are in force, read by the terms gate (Identity) and provided by Content. A type that
 * was never published is not in the result, so it is not required of anyone.
 */
interface ActiveLegalVersions
{
    /** @return array<string, int> document type (`terms`, `privacy`) mapped to its active version */
    public function active(): array;
}
