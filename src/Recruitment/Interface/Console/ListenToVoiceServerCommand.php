<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Infrastructure\Voice\AriEventListener;
use Throwable;

/**
 * Runs for as long as the application runs, under a process manager (Supervisor). Phone interviews work only while it runs: the voice server hands every call
 * and every team browser to this application as a Stasis application and nothing moves without it.
 */
final class ListenToVoiceServerCommand extends Command
{
    protected $signature = 'interview:voice-listen';

    protected $description = 'Listen to the voice server (Asterisk ARI events) and record the calls of phone interviews';

    /** Seconds to wait before connecting again after the connection was lost. */
    private const int RECONNECT_SECONDS = 3;

    public function handle(AriEventListener $listener): int
    {
        if (! $listener->isConfigured()) {
            $this->error('The voice server is not configured (ASTERISK_ARI_URL, ASTERISK_ARI_USER, ASTERISK_ARI_PASSWORD).');

            return self::FAILURE;
        }

        while (true) {
            try {
                $listener->listen();
            } catch (Throwable $e) {
                $this->warn('Voice server connection lost: '.$e->getMessage());
            }

            sleep(self::RECONNECT_SECONDS);
        }
    }
}
