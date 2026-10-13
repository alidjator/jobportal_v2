<?php

namespace Src\Identity\Application\Admin;

final readonly class AdminRoleSummary
{
    /** @param  list<string>  $permissions sorted by name */
    public function __construct(
        public string $name,
        public array $permissions,
        public int $adminCount,
    ) {}
}
