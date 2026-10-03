<?php

namespace Src\Notification\Domain;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationReader
{
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;
}
