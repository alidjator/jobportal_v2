<?php

namespace Src\Shared\Application\Settings;

use InvalidArgumentException;

/**
 * Every admin-configurable setting of the application. Each context registers its own from its service provider (like the
 * permission catalog), so contexts do not know each other and Platform only provides the mechanism.
 */
final class SettingsCatalog
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function add(SettingDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $definition->key)) {
                throw new InvalidArgumentException("Setting key {$definition->key} must look like area.name in lower snake case.");
            }

            if (isset($this->definitions[$definition->key])) {
                throw new InvalidArgumentException("Setting {$definition->key} is already registered.");
            }

            // A default that the setting would refuse is a programming error: find it when the catalog is built.
            if ($definition->normalize($definition->default) !== $definition->default) {
                throw new InvalidArgumentException("The default of {$definition->key} is not in its canonical form.");
            }

            $this->definitions[$definition->key] = $definition;
        }
    }

    public function find(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return list<SettingDefinition> sorted by key */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return array_values($definitions);
    }
}
