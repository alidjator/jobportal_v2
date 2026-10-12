<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of video interviews (`interview.*`), registered in the shared catalog by the Recruitment service provider. */
final class InterviewSettings
{
    public const string VIDEO_ENABLED = 'interview.video_enabled';

    public const string VIDEO_MAX_CONCURRENT_ROOMS = 'interview.video_max_concurrent_rooms';

    public const string RECORDING_RETENTION_DAYS = 'interview.recording_retention_days';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::boolean(
                self::VIDEO_ENABLED, false,
                'Whether interviews can be held in the platform (format video). While off, scheduling one is refused.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::VIDEO_MAX_CONCURRENT_ROOMS, 3, 1, 50,
                'The most video interview rooms open at the same time; a join that would open one more is refused until a room closes.',
            ),
            SettingDefinition::integer(
                self::RECORDING_RETENTION_DAYS, 90, 1, 365,
                'How many days after the interview its recordings are kept before they are deleted permanently.',
            ),
        ];
    }
}
