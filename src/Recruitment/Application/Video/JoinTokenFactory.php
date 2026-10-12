<?php

namespace Src\Recruitment\Application\Video;

/** The token that lets one member or the candidate join the room of an interview. The same arguments always give the same token, so it can be removed later. */
interface JoinTokenFactory
{
    public function make(int $interviewId, int $roomId, int $userId): string;

    /** The name the browser shows and sends when it joins: the user id with a signature, so the attendance the video server reports cannot be forged. */
    public function display(int $interviewId, int $roomId, int $userId): string;

    /** The user a display name was made for in this room, or null when it was not made by {@see self::display()}. */
    public function userOfDisplay(string $display, int $interviewId, int $roomId): ?int;
}
