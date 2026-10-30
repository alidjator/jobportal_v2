<?php

namespace Tests\Unit\Recruitment;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Recruitment\Application\Voice\ListenToVoiceServer;
use Src\Recruitment\Application\Voice\VoiceEventStream;

final class ListenToVoiceServerTest extends TestCase
{
    public function test_it_reports_whether_the_voice_server_is_configured(): void
    {
        self::assertTrue((new ListenToVoiceServer($this->stream(true)))->isConfigured());
        self::assertFalse((new ListenToVoiceServer($this->stream(false)))->isConfigured());
    }

    public function test_it_listens_once_per_call_and_returns_when_the_connection_ends(): void
    {
        $stream = $this->stream(true);

        (new ListenToVoiceServer($stream))->execute();
        (new ListenToVoiceServer($stream))->execute();

        self::assertSame(2, $stream->listened);
    }

    public function test_a_lost_connection_reaches_the_caller_that_connects_again(): void
    {
        $this->expectException(RuntimeException::class);

        (new ListenToVoiceServer($this->stream(true, fails: true)))->execute();
    }

    /** @return VoiceEventStream&object{listened: int} */
    private function stream(bool $configured, bool $fails = false): VoiceEventStream
    {
        return new class($configured, $fails) implements VoiceEventStream
        {
            public int $listened = 0;

            public function __construct(private readonly bool $configured, private readonly bool $fails) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function listen(): void
            {
                $this->listened++;

                if ($this->fails) {
                    throw new RuntimeException('Connection lost.');
                }
            }
        };
    }
}
