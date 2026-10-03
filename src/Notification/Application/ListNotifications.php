<?php

namespace Src\Notification\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Notification\Domain\NotificationReader;

final class ListNotifications
{
    public const PER_PAGE = 10;

    public function __construct(private readonly NotificationReader $reader) {}

    public function __invoke(int $userId): LengthAwarePaginator
    {
        return $this->reader->paginateForUser($userId, self::PER_PAGE);
    }
}
