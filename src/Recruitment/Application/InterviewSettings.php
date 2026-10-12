<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of video and voice interviews (`interview.*`), registered in the shared catalog by the Recruitment service provider. */
final class InterviewSettings
{
    public const string VIDEO_ENABLED = 'interview.video_enabled';

    public const string VIDEO_MAX_CONCURRENT_ROOMS = 'interview.video_max_concurrent_rooms';

    public const string RECORDING_RETENTION_DAYS = 'interview.recording_retention_days';

    public const string VOICE_ENABLED = 'interview.voice_enabled';

    public const string VOICE_MAX_CALL_MINUTES = 'interview.voice_max_call_minutes';

    public const string VOICE_MONTHLY_MINUTES = 'interview.voice_monthly_minutes';

    public const string VOICE_MAX_ATTEMPTS = 'interview.voice_max_attempts';

    public const string VOICE_MAX_CONCURRENT_CALLS = 'interview.voice_max_concurrent_calls';

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
            SettingDefinition::boolean(
                self::VOICE_ENABLED, false,
                'Whether interviews can be held as a phone call from the platform (format voice). While off, scheduling one is refused.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::VOICE_MAX_CALL_MINUTES, 60, 5, 240,
                'The longest one phone interview call can last; the voice server hangs up when it is reached.',
            ),
            SettingDefinition::integer(
                self::VOICE_MONTHLY_MINUTES, 600, 1, 100000,
                'The call minutes a company can use for phone interviews in a calendar month (Jakarta time); a call that would exceed it is refused. A guard, not billing.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::VOICE_MAX_ATTEMPTS, 3, 1, 10,
                'How many times the team can dial the candidate of one phone interview when the calls are not answered.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::VOICE_MAX_CONCURRENT_CALLS, 5, 1, 50,
                'The most phone interview calls in progress at the same time; a call that would exceed it is refused until one ends.',
            ),
        ];
    }
}
