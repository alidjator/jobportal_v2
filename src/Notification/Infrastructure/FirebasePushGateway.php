<?php

namespace Src\Notification\Infrastructure;

use Illuminate\Contracts\Container\Container;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Src\Notification\Domain\PushFailed;
use Src\Notification\Domain\PushGateway;
use Throwable;

/** Adaptor FCM (kreait/laravel-firebase). Client dibuat lazy saat pengiriman, bukan di constructor/boot. */
class FirebasePushGateway implements PushGateway
{
    private const TOPIC = 'topic-A';

    private const MULTICAST_LIMIT = 500;

    public function __construct(private readonly Container $container) {}

    public function send(string $title, string $body, array $tokens): array
    {
        try {
            $messaging = $this->container->make(Messaging::class);
            $message = CloudMessage::new()->withTopic(self::TOPIC)
                ->withNotification(Notification::fromArray(['title' => $title, 'body' => $body]));

            $success = 0;
            $failure = 0;
            foreach (array_chunk($tokens, self::MULTICAST_LIMIT) as $chunk) {
                $report = $messaging->sendMulticast($message, $chunk);
                $success += $report->successes()->count();
                $failure += $report->failures()->count();
            }

            return ['success_count' => $success, 'failure_count' => $failure];
        } catch (Throwable $e) {
            report($e);

            throw new PushFailed($e->getMessage(), 0, $e);
        }
    }
}
