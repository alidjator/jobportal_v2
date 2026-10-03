<?php

namespace Src\Notification\Interface\Http\Requests;

use Src\Notification\Domain\BroadcastAuthorizer;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Otorisasi admin dicek sebelum validasi (403 lebih dulu dari 422, seperti aplikasi lama). */
class BroadcastPushRequest extends PushMessageRequest
{
    public function authorize(): bool
    {
        return app(BroadcastAuthorizer::class)->canBroadcast();
    }

    protected function failedAuthorization(): never
    {
        throw new AccessDenied('Forbidden');
    }
}
