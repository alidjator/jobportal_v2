<?php

namespace Src\Notification\Domain;

interface PushGateway
{
    /**
     * Kirim push ke daftar device token (topic 'topic-A' + multicast, seperti aplikasi lama).
     *
     * @param  list<string>  $tokens
     * @return array<string, mixed> ringkasan hasil pengiriman
     *
     * @throws PushFailed bila pengiriman gagal
     */
    public function send(string $title, string $body, array $tokens): array;
}
