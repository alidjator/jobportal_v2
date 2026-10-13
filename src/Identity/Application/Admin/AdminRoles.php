<?php

namespace Src\Identity\Application\Admin;

final class AdminRoles
{
    /** Holds every registered permission; kept in step with the catalog by `SyncAdminPermissions`. It cannot be edited or deleted from the console. */
    public const string SUPER_ADMIN = 'super_admin';

    /** The name of a role an operator creates: 3 to 50 lowercase letters, digits or underscores, starting with a letter. */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]{2,49}$/';
}
