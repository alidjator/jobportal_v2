<?php

namespace Src\Notification\Application;

use Src\Notification\Domain\DeviceTokenRepository;
use Throwable;

final class StoreDeviceToken
{
    public function __construct(private readonly DeviceTokenRepository $tokens) {}

    /** @return array<string, mixed> isi `data` respons */
    public function __invoke(?int $userId, string $token): array
    {
        try {
            $row = $this->tokens->store($userId, $token);
        } catch (Throwable $e) {
            report($e);

            return ['message' => $e->getMessage()];
        }

        return ['message' => 'Token successfully stored!', 'device_token' => $row];
    }
}
