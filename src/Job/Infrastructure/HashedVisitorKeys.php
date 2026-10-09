<?php

namespace Src\Job\Infrastructure;

use Illuminate\Contracts\Config\Repository as Config;
use Src\Job\Application\Views\VisitorKeys;

/** An HMAC of the address and the browser, keyed by the application key and the day: one key per visitor per day, nothing that identifies the visitor beyond it. */
final readonly class HashedVisitorKeys implements VisitorKeys
{
    public function __construct(private Config $config) {}

    public function forVisitor(string $address, string $userAgent, string $day): string
    {
        $secret = (string) $this->config->get('app.key').'|'.$day;

        return 'v'.substr(hash_hmac('sha256', $address.'|'.$userAgent, $secret), 0, 40);
    }
}
