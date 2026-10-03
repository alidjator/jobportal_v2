<?php

namespace Src\Notification\Infrastructure\Persistence;

use Src\Notification\Domain\DeviceTokenRepository;
use Src\Notification\Infrastructure\Persistence\Eloquent\DeviceTokenModel;

class EloquentDeviceTokenRepository implements DeviceTokenRepository
{
    public function store(?int $userId, string $token): array
    {
        $keys = $userId === null
            ? ['device_token' => $token]
            : ['user_id' => $userId, 'device_token' => $token];

        return DeviceTokenModel::firstOrCreate($keys)->toArray();
    }

    public function tokensForUser(int $userId): array
    {
        return DeviceTokenModel::where('user_id', $userId)->pluck('device_token')->all();
    }

    public function allTokens(): array
    {
        return DeviceTokenModel::pluck('device_token')->all();
    }
}
