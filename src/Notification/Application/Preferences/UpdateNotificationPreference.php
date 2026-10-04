<?php

namespace Src\Notification\Application\Preferences;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Stores the actor's choice for one kind of notification on one channel. An explicit value is kept even when it equals the default. */
final readonly class UpdateNotificationPreference
{
    public function __construct(
        private NotificationPreferenceCatalog $catalog,
        private NotificationPreferenceStore $store,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `unknown-notification-preference` for a key that is not registered or a channel it does not support */
    public function execute(Actor $actor, string $key, string $channel, bool $enabled): void
    {
        $definition = $this->catalog->find($key);
        $parsed = NotificationChannel::tryFrom($channel);

        if ($definition === null || $parsed === null || ! $definition->supports($parsed)) {
            throw new BusinessRuleViolation('This notification setting does not exist.', 'unknown-notification-preference');
        }

        $this->store->set((int) $actor->id, $key, $parsed->value, $enabled, $this->clock->now());
    }
}
