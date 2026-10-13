<?php

namespace Src\Identity\Interface\Console;

/** The operating-system user who runs an admin-management command, recorded with the audit entry. */
trait NamesTheOperator
{
    private function operator(): string
    {
        foreach (['SUDO_USER', 'USER', 'LOGNAME'] as $variable) {
            $name = getenv($variable);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'unknown';
    }
}
