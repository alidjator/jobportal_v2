<?php

namespace Src\Notification\Application\Preferences;

use InvalidArgumentException;
use Src\Shared\Application\Accounts\AccountActivation;
use Src\Shared\Application\Company\SuspendedMembers;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * The user's own choice for a key and channel when there is one, otherwise the catalog default. A suspended company member and, for a scheduled email, an imported
 * account that has not signed in yet get nothing by email or push.
 */
final readonly class StoredNotificationPreferences implements NotificationPreferences
{
    public function __construct(
        private NotificationPreferenceCatalog $catalog,
        private NotificationPreferenceStore $store,
        private SuspendedMembers $suspended,
        private AccountActivation $activation,
    ) {}

    public function isEnabled(int $userId, string $key, NotificationChannel $channel): bool
    {
        $definition = $this->definition($key, $channel);

        // A suspended company member gets no email or push either.
        if ($this->suspended->among([$userId]) !== []) {
            return false;
        }

        if ($this->isHeld($definition, $channel) && $this->activation->heldAmong([$userId]) !== []) {
            return false;
        }

        return $this->store->stored($userId)["{$key}.{$channel->value}"] ?? $definition->defaultFor($channel);
    }

    public function enabledAmong(array $userIds, string $key, NotificationChannel $channel): array
    {
        $definition = $this->definition($key, $channel);
        $default = $definition->defaultFor($channel);
        $stored = $this->store->storedFor($userIds);
        $userIds = array_values(array_diff($userIds, $this->suspended->among($userIds)));

        if ($this->isHeld($definition, $channel)) {
            $userIds = array_values(array_diff($userIds, $this->activation->heldAmong($userIds)));
        }

        return array_values(array_filter($userIds, static fn (int $userId): bool => $stored[$userId]["{$key}.{$channel->value}"] ?? $default));
    }

    /** Only the email channel is held: push belongs to a device the person registered by signing in. */
    private function isHeld(NotificationPreferenceDefinition $definition, NotificationChannel $channel): bool
    {
        return $definition->scheduled && $channel === NotificationChannel::Email;
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
