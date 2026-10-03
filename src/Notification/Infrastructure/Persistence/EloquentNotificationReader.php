<?php

namespace Src\Notification\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Src\Notification\Domain\NotificationReader;

/**
 * Membaca tabel `notifications` (Laravel database notifications) milik user.
 * Aplikasi lama menyimpan `notifiable_type` = App\Models\User; user baru
 * (Src\Identity...\UserModel) dicakup juga agar riwayat lama tetap terbaca.
 */
class EloquentNotificationReader implements NotificationReader
{
    private const NOTIFIABLE_TYPES = [
        'App\Models\User',
        'Src\Identity\Infrastructure\Persistence\UserModel',
    ];

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return DatabaseNotification::query()
            ->whereIn('notifiable_type', self::NOTIFIABLE_TYPES)
            ->where('notifiable_id', $userId)
            ->latest()
            ->paginate($perPage);
    }
}
