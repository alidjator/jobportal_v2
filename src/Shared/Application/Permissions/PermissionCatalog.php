<?php

namespace Src\Shared\Application\Permissions;

use InvalidArgumentException;

/**
 * The admin permissions of the whole application. Each context registers its own from its service provider, so contexts do not
 * know each other; Identity reads the catalog to create the permissions and to give `super_admin` all of them.
 */
final class PermissionCatalog
{
    /** @var array<string, PermissionDefinition> */
    private array $definitions = [];

    public function add(PermissionDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            if (isset($this->definitions[$definition->name]) && $this->definitions[$definition->name]->group !== $definition->group) {
                throw new InvalidArgumentException("Permission {$definition->name} is already registered in another group.");
            }

            $this->definitions[$definition->name] = $definition;
        }
    }

    /** @return list<PermissionDefinition> sorted by name */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return array_values($definitions);
    }
}
