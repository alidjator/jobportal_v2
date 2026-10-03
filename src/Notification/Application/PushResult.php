<?php

namespace Src\Notification\Application;

final readonly class PushResult
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data, public int $status = 200) {}
}
