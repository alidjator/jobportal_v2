<?php

namespace Src\Identity\Application\Admin;

/** Every admin role with its permissions and how many administrators hold it, for the operator. */
final readonly class ListAdminRoles
{
    public function __construct(private AdminDirectory $directory) {}

    /** @return list<AdminRoleSummary> */
    public function execute(): array
    {
        return $this->directory->roles();
    }
}
