<?php

namespace Src\Identity\Application\Admin;

/** Reads the administrators and their roles for the operator commands. The RBAC package stays behind this interface (Infrastructure). */
interface AdminDirectory
{
    /** @return list<AdminRoleSummary> sorted by name */
    public function roles(): array;

    public function role(string $name): ?AdminRoleSummary;

    /** @return list<AdminSummary> sorted by email */
    public function admins(): array;

    /** The role of the administrator, or null when they hold none. */
    public function roleOf(int $adminId): ?string;

    /** The active administrators who hold the role; 0 when the role does not exist. */
    public function countActiveAdminsWithRole(string $role): int;
}
