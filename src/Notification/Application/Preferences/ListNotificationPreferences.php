<?php

namespace Src\Notification\Application\Preferences;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;

/** The part of the catalog meant for the actor's kind of account, with the effective value per channel. Not paginated: the catalog is small and fixed by the code. */
final readonly class ListNotificationPreferences
{
    public function __construct(
        private NotificationPreferenceCatalog $catalog,
        private NotificationPreferenceStore $store,
    ) {}

    /** @return list<NotificationPreferenceView> */
    public function execute(Actor $actor): array
    {
        $stored = $this->store->stored((int) $actor->id);
        $views = [];

        foreach ($this->catalog->all() as $definition) {
            if (! $definition->isFor($actor->kind)) {
                continue;
            }

            $channels = [];
            foreach ($definition->defaults as $channel => $default) {
                $own = $stored["{$definition->key}.{$channel}"] ?? null;
                $channels[$channel] = ['enabled' => $own ?? $default, 'is_default' => $own === null];
            }
            $views[] = new NotificationPreferenceView($definition->key, $definition->description, $channels, $definition->audiences);
        }

        return $views;
    }
}
