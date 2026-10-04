<?php

namespace Src\Notification\Application\Preferences;

/** One kind of notification with the value in force for each supported channel; `isDefault` is true while the user has not chosen. */
final readonly class NotificationPreferenceView
{
    /** @param  array<string, array{enabled: bool, is_default: bool}>  $channels keyed by channel value */
    public function __construct(
        public string $key,
        public string $description,
        public array $channels,
    ) {}
}
