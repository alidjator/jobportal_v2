<?php

namespace Src\Recruitment\Domain\Video;

/** What a recording holds: a video room (converted from the video server's tracks) or a phone call (fetched from the voice server). */
enum RecordingMedia: string
{
    case Video = 'video';
    case Audio = 'audio';
}
