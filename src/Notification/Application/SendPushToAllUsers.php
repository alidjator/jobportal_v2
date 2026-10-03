<?php

namespace Src\Notification\Application;

use Src\Notification\Domain\BroadcastAuthorizer;
use Src\Notification\Domain\DeviceTokenRepository;
use Src\Notification\Domain\PushFailed;
use Src\Notification\Domain\PushGateway;
use Src\Shared\Domain\Exceptions\AccessDenied;

final class SendPushToAllUsers
{
    public function __construct(
        private readonly BroadcastAuthorizer $authorizer,
        private readonly DeviceTokenRepository $tokens,
        private readonly PushGateway $push,
    ) {}

    public function authorize(): void
    {
        if (! $this->authorizer->canBroadcast()) {
            throw new AccessDenied('Forbidden');
        }
    }

    public function __invoke(string $title, string $body): PushResult
    {
        $this->authorize();

        $tokens = $this->tokens->allTokens();

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
