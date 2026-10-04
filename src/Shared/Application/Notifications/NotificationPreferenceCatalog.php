<?php

namespace Src\Shared\Application\Notifications;

use InvalidArgumentException;

/**
 * Every kind of notification whose email or push delivery a user can switch. Each context registers its own from its service
 * provider (like settings and permissions), so Notification only provides the mechanism.
 */
final class NotificationPreferenceCatalog
{
    /** @var array<string, NotificationPreferenceDefinition> */
    private array $definitions = [];

    public function add(NotificationPreferenceDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $definition->key) || strlen($definition->key) > 64) {
                throw new InvalidArgumentException("Notification preference key {$definition->key} must be lower snake case, at most 64 characters.");
            }

            if (isset($this->definitions[$definition->key])) {
                throw new InvalidArgumentException("Notification preference {$definition->key} is already registered.");
            }

            $this->definitions[$definition->key] = $definition;
        }
    }

    public function find(string $key): ?NotificationPreferenceDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return list<NotificationPreferenceDefinition> sorted by key */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return array_values($definitions);
    }
}
