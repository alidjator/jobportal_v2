<?php

namespace Src\Notification\Infrastructure;

use Src\Notification\Domain\BroadcastAuthorizer;

/**
 * Hanya admin (guard `admin`) yang boleh broadcast. Admin belum bisa memegang
 * token Sanctum, jadi untuk request API endpoint ini praktis nonaktif.
 */
class AdminBroadcastAuthorizer implements BroadcastAuthorizer
{
    public function canBroadcast(): bool
    {
        return array_key_exists('admin', config('auth.guards', [])) && auth('admin')->check();
    }
}
