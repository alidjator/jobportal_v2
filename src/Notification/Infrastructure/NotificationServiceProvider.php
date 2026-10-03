<?php

namespace Src\Notification\Infrastructure;

use Src\Notification\Domain\BroadcastAuthorizer;
use Src\Notification\Domain\DeviceTokenRepository;
use Src\Notification\Domain\NotificationReader;
use Src\Notification\Domain\PushGateway;
use Src\Notification\Infrastructure\Persistence\EloquentDeviceTokenRepository;
use Src\Notification\Infrastructure\Persistence\EloquentNotificationReader;
use Src\Shared\Infrastructure\ContextServiceProvider;

class NotificationServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        DeviceTokenRepository::class => EloquentDeviceTokenRepository::class,
        NotificationReader::class => EloquentNotificationReader::class,
        PushGateway::class => FirebasePushGateway::class,
        BroadcastAuthorizer::class => AdminBroadcastAuthorizer::class,
    ];
}
