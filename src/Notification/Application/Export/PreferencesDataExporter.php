<?php

namespace Src\Notification\Application\Export;

use Src\Notification\Application\Preferences\ListNotificationPreferences;
use Src\Notification\Application\Preferences\NotificationPreferenceView;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The Notification part of the personal data export: the value in force for each kind of notification and channel, and whether it is the default or the job seeker's own choice. */
final readonly class PreferencesDataExporter implements PersonalDataExporter
{
    public function __construct(private ListNotificationPreferences $preferences) {}

    public function key(): string
    {
        return 'notification_preferences';
    }

    public function export(int $userId): PersonalDataSection
    {
        return new PersonalDataSection(array_map(static fn (NotificationPreferenceView $view): array => [
            'kind' => $view->key,
            'description' => $view->description,
            'channels' => $view->channels,
        ], $this->preferences->execute(Actor::jobSeeker($userId))));
    }
}
