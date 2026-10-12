<?php

namespace Src\Recruitment\Application\Video;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** The video server (Janus VideoRoom): rooms and the tokens that let a browser join them. */
interface VideoRoomGateway
{
    /**
     * Creates a private room that only people with a token and the pin can join, and returns its number.
     *
     * @throws ExternalServiceUnavailable
     */
    public function createRoom(string $pin, string $secret, int $maxParticipants): int;

    /**
     * Closes a room; a room that no longer exists counts as closed.
     *
     * @throws ExternalServiceUnavailable
     */
    public function destroyRoom(int $roomId, string $secret): void;

    /**
     * Starts recording every stream of the room into the directory (as the video server sees it).
     *
     * @throws ExternalServiceUnavailable
     */
    public function startRecording(int $roomId, string $secret, string $directory): void;

    /** @throws ExternalServiceUnavailable */
    public function stopRecording(int $roomId, string $secret): void;

    /** @throws ExternalServiceUnavailable */
    public function addToken(string $token): void;

    /**
     * Removes a token; a token that is not known counts as removed.
     *
     * @throws ExternalServiceUnavailable
     */
    public function removeToken(string $token): void;

    /**
     * The address browsers connect to.
     *
     * @throws ExternalServiceUnavailable
     */
    public function websocketUrl(): string;
}
