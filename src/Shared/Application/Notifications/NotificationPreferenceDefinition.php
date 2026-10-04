<?php

namespace Src\Shared\Application\Notifications;

use InvalidArgumentException;

/**
 * One kind of notification a user can tune, for example `application_update`. `defaults` lists the channels the kind supports and
 * whether each one is on until the user decides otherwise; a channel that is not listed is not offered for this kind.
 */
final readonly class NotificationPreferenceDefinition
{
    /** @param  array<string, bool>  $defaults channel value (`email`, `push`) => on by default */
    public function __construct(
        public string $key,
        public string $description,
        public array $defaults,
    ) {
        if ($defaults === []) {
            throw new InvalidArgumentException("Notification preference {$key} must support at least one channel.");
        }

        foreach (array_keys($defaults) as $channel) {
            if (NotificationChannel::tryFrom((string) $channel) === null) {
                throw new InvalidArgumentException("Notification preference {$key} lists the unknown channel {$channel}.");
            }
        }
    }

    public function supports(NotificationChannel $channel): bool
    {
        return array_key_exists($channel->value, $this->defaults);
    }

    public function defaultFor(NotificationChannel $channel): bool
    {
        return $this->defaults[$channel->value] ?? false;
    }
}
