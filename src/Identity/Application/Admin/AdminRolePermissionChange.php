<?php

namespace Src\Identity\Application\Admin;

/** What replacing the permissions of a role adds and removes. */
final readonly class AdminRolePermissionChange
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public array $added,
        public array $removed,
    ) {}

    public function isEmpty(): bool
    {
        return $this->added === [] && $this->removed === [];
    }
}
