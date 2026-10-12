<?php

namespace Src\Recruitment\Infrastructure\Voice;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\Voice\HandleAriEvent;
use Src\Recruitment\Application\Voice\VoiceNames;
use Throwable;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Message\Text;

/**
 * Listens to the event stream of the voice server (ARI over a WebSocket) as the `jobportal` Stasis application and hands every event to {@see HandleAriEvent}.
 * It runs for as long as the connection lives and returns when the connection is lost; the command that owns it reconnects. One bad event is logged and
 * skipped, never the end of the listener.
 */
final readonly class AriEventListener
{
    public function __construct(
        private HandleAriEvent $handler,
        private LoggerInterface $logger,
        private ?string $ariUrl,
        private ?string $user,
        private ?string $password,
    ) {}

    /** Whether the voice server is configured at all. */
    public function isConfigured(): bool
    {
        return filled($this->ariUrl) && filled($this->user) && filled($this->password);
    }

    /** Connects and listens until the connection ends. */
    public function listen(): void
    {
        $client = new Client($this->streamUrl());
        $client->setTimeout(30);
        $client->onText(function (Client $client, Connection $connection, Message $message): void {
            if ($message instanceof Text) {
                $this->handleMessage($message->getContent());
            }
        });
        $client->start();
    }

    /** One text frame of the stream: a JSON event. */
    public function handleMessage(string $json): void
    {
        try {
            $event = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($event)) {
                $this->handler->execute($event);
            }
        } catch (Throwable $e) {
            $this->logger->error('A voice server event could not be handled.', ['exception' => $e]);
        }
    }

    /** The WebSocket address of the event stream: the ARI address with `ws`, the application name and the credentials. */
    public function streamUrl(): string
    {
        $base = (string) preg_replace('#^http#', 'ws', rtrim((string) $this->ariUrl, '/'));

        return $base.'/events?'.http_build_query(['app' => VoiceNames::APP, 'api_key' => $this->user.':'.$this->password, 'subscribeAll' => 'false']);
    }
}
