<?php

namespace Src\Notification\Domain;

/** Menentukan siapa yang boleh mengirim push ke seluruh device (hanya admin). */
interface BroadcastAuthorizer
{
    public function canBroadcast(): bool;
}
