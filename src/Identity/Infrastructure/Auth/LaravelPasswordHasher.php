<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Contracts\Hashing\Hasher;
use Src\Identity\Application\Auth\PasswordHasher;

final class LaravelPasswordHasher implements PasswordHasher
{
    private ?string $dummyHash = null;

    public function __construct(private readonly Hasher $hasher) {}

    public function make(string $plain): string
    {
        return $this->hasher->make($plain);
    }

    public function check(string $plain, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            $this->hasher->check($plain, $this->dummyHash ??= $this->hasher->make('timing-equalisation'));

            return false;
        }

        return $this->hasher->check($plain, $hash);
    }
}
