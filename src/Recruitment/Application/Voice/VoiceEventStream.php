<?php

namespace Src\Recruitment\Application\Voice;

/** The event stream of the voice server (ARI over a WebSocket); the infrastructure connects, {@see HandleAriEvent} handles what arrives. */
interface VoiceEventStream
{
    /** Whether the voice server is configured at all. */
    public function isConfigured(): bool;

    /** Connects and listens until the connection ends (it returns or throws when the connection is lost). */
    public function listen(): void;
}
