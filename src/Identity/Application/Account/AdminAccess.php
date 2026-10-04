<?php

namespace Src\Identity\Application\Account;

/** The roles of an administrator and the effective permissions (from roles and direct grants), as sorted names without duplicates. */
final readonly class AdminAccess
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public array $roles,
        public array $permissions,
    ) {}
}
