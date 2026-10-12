<?php

namespace Src\Recruitment\Application\Voice;

/** How the things of one interview are named on the voice server. They are derived, so no id has to be stored. */
final class VoiceNames
{
    public const string APP = 'jobportal';

    public static function bridge(int $interviewId): string
    {
        return 'interview-'.$interviewId;
    }

    /** The interview of a bridge name, or null for any other name. */
    public static function interviewOfBridge(string $bridgeId): ?int
    {
        return preg_match('/^interview-(\d{1,18})$/', $bridgeId, $match) === 1 ? (int) $match[1] : null;
    }

    public static function endpoint(int $interviewId, int $userId): string
    {
        return 't'.$interviewId.'-'.$userId;
    }

    /** The account name inside a channel name such as `PJSIP/t12-5-00000003`, or null for a channel that is not a team browser. */
    public static function endpointOfChannelName(string $channelName): ?string
    {
        return preg_match('#^PJSIP/(t\d{1,18}-\d{1,18})-[0-9a-f]+$#', $channelName, $match) === 1 ? $match[1] : null;
    }
}
