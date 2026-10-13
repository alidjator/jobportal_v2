<?php

namespace Src\Identity\Application\Admin;

/** Every administrator with role, status, second factor and last sign-in, for the operator. */
final readonly class ListAdmins
{
    public function __construct(private AdminDirectory $directory) {}

    /** @return list<AdminSummary> */
    public function execute(): array
    {
        return $this->directory->admins();
    }
}
