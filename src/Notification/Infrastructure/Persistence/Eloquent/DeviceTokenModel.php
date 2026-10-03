<?php

namespace Src\Notification\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Memetakan tabel `device_tokens` yang sudah ada. */
class DeviceTokenModel extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = ['device_token', 'user_id'];
}
