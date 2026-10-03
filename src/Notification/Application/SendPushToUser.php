<?php

namespace Src\Notification\Application;

use Src\Notification\Domain\DeviceTokenRepository;
use Src\Notification\Domain\PushFailed;
use Src\Notification\Domain\PushGateway;

final class SendPushToUser
{
    public function __construct(private readonly DeviceTokenRepository $tokens, private readonly PushGateway $push) {}

    public function __invoke(int $userId, string $title, string $body): PushResult
    {
        $tokens = $this->tokens->tokensForUser($userId);

        if ($tokens === []) {
            return new PushResult(['message' => 'Not device token found']);
        }

        try {
            return new PushResult(['response' => $this->push->send($title, $body, $tokens)]);
        } catch (PushFailed $e) {
            return new PushResult(['message' => $e->getMessage()], 500);
        }
    }
}
