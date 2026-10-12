<?php

namespace Src\Recruitment\Infrastructure\Video;

use Src\Recruitment\Application\Video\JoinTokenFactory;

/**
 * A token is a keyed hash of the interview, the room and the user, so it is unguessable, differs for every room and needs no storage to be removed later. The
 * display name is the user id and a short keyed signature of the same three numbers.
 */
final readonly class HmacJoinTokenFactory implements JoinTokenFactory
{
    public function __construct(private string $key) {}

    public function make(int $interviewId, int $roomId, int $userId): string
    {
        return hash_hmac('sha256', "video-join|{$interviewId}|{$roomId}|{$userId}", $this->key);
    }

    public function display(int $interviewId, int $roomId, int $userId): string
    {
        return $userId.'.'.$this->signature($interviewId, $roomId, $userId);
    }

    public function userOfDisplay(string $display, int $interviewId, int $roomId): ?int
    {
        if (preg_match('/^(\d{1,18})\.([0-9a-f]{16})$/', $display, $parts) !== 1) {
            return null;
        }

        $userId = (int) $parts[1];

        return hash_equals($this->signature($interviewId, $roomId, $userId), $parts[2]) ? $userId : null;
    }

    private function signature(int $interviewId, int $roomId, int $userId): string
    {
        return substr(hash_hmac('sha256', "video-display|{$interviewId}|{$roomId}|{$userId}", $this->key), 0, 16);
    }
}
