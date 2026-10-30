<?php

namespace Src\Recruitment\Application\Voice;

/**
 * Listens to the voice server for as long as one connection lives. Phone interviews work only while something runs this: the voice server hands every call and every team browser to the
 * application as a Stasis application. The caller (the console command under Supervisor) connects again when this returns or throws.
 */
final readonly class ListenToVoiceServer
{
    public function __construct(private VoiceEventStream $stream) {}

    public function isConfigured(): bool
    {
        return $this->stream->isConfigured();
    }

    /** Returns when the connection ends. */
    public function execute(): void
    {
        $this->stream->listen();
    }
}
