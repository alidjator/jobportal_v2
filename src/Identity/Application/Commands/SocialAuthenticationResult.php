<?php

namespace Src\Identity\Application\Commands;

final readonly class SocialAuthenticationResult
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data, public int $status = 200) {}
}
