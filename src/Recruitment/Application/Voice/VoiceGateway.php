<?php

namespace Src\Recruitment\Application\Voice;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * The voice server (Asterisk, driven through its ARI): a mixing bridge per interview, a temporary browser account for each team member, the dialled call to the
 * candidate, and what the event listener does with the channels it hears about. Every method may fail with {@see ExternalServiceUnavailable}; removing
 * something that no longer exists counts as removed.
 */
interface VoiceGateway
{
    /** @throws ExternalServiceUnavailable */
    public function openBridge(string $bridgeId): void;

    /** @throws ExternalServiceUnavailable */
    public function closeBridge(string $bridgeId): void;

    /**
     * Creates or replaces the browser account `$username` (WebRTC over WSS, only allowed into the team dialplan).
     *
     * @throws ExternalServiceUnavailable
     */
    public function provisionEndpoint(string $username, string $password): void;

    /** @throws ExternalServiceUnavailable */
    public function removeEndpoint(string $username): void;

    /**
     * How many channels (the team's browsers, the candidate's phone) are in the bridge; 0 for a bridge that does not exist.
     *
     * @throws ExternalServiceUnavailable
     */
    public function channelsInBridge(string $bridgeId): int;

    /**
     * Dials the number through the trunk as the platform number, on a channel with the given id (its events carry that id) that will be handed to the
     * event listener when answered.
     *
     * @throws ExternalServiceUnavailable
     */
    public function dial(string $channelId, string $number, string $callId): void;

    /** @throws ExternalServiceUnavailable */
    public function hangup(string $channelId): void;

    /** @throws ExternalServiceUnavailable */
    public function answer(string $channelId): void;

    /** @throws ExternalServiceUnavailable */
    public function addToBridge(string $bridgeId, string $channelId): void;

    /**
     * Makes the voice server hang up the channel after this many seconds.
     *
     * @throws ExternalServiceUnavailable
     */
    public function limitDuration(string $channelId, int $seconds): void;

    /** The domain browsers register with (the host of the SIP URI). */
    public function sipDomain(): string;

    /**
     * The address browsers connect to.
     *
     * @throws ExternalServiceUnavailable
     */
    public function websocketUrl(): string;
}
