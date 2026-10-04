<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Container\Attributes\Config;
use Src\Identity\Application\Auth\VerificationCodeHasher;

/**
 * Keyed hash (HMAC-SHA-256 with the application key). Codes are short-lived and limited to 5 wrong attempts, so a fast
 * keyed hash is enough; the key keeps a leaked table from being matched against all one million codes without the secret.
 */
final readonly class HmacVerificationCodeHasher implements VerificationCodeHasher
{
    public function __construct(#[Config('app.key')] private string $key) {}

    public function make(string $code): string
    {
        return hash_hmac('sha256', $code, $this->key);
    }

    public function matches(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->make($code));
    }
}
