<?php

namespace Src\Notification\Application\Preferences;

use InvalidArgumentException;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Notifications\NotificationPreferences;

/** The user's own choice for a key and channel when there is one, otherwise the catalog default. */
final readonly class StoredNotificationPreferences implements NotificationPreferences
{
    public function __construct(
        private NotificationPreferenceCatalog $catalog,
        private NotificationPreferenceStore $store,
    ) {}

    public function isEnabled(int $userId, string $key, NotificationChannel $channel): bool
    {
        $definition = $this->definition($key, $channel);

        return $this->store->stored($userId)["{$key}.{$channel->value}"] ?? $definition->defaultFor($channel);
    }

    public function enabledAmong(array $userIds, string $key, NotificationChannel $channel): array
    {
        $default = $this->definition($key, $channel)->defaultFor($channel);
        $stored = $this->store->storedFor($userIds);

        return array_values(array_filter($userIds, static fn (int $userId): bool => $stored[$userId]["{$key}.{$channel->value}"] ?? $default));
    }

    private function definition(string $key, NotificationChannel $channel): NotificationPreferenceDefinition
    {
        $definition = $this->catalog->find($key)
            ?? throw new InvalidArgumentException("Notification preference {$key} is not registered.");

        if (! $definition->supports($channel)) {
            throw new InvalidArgumentException("Notification preference {$key} does not support the {$channel->value} channel.");
        }

        return $definition;
    }
}
