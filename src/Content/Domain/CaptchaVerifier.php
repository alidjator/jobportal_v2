<?php

namespace Src\Content\Domain;

interface CaptchaVerifier
{
    public function isActive(): bool;

    public function verify(?string $response, ?string $ip = null): bool;
}
