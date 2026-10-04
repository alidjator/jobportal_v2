<?php

namespace Src\Identity\Application\Auth;

interface PasswordHasher
{
    public function make(string $plain): string;

    /**
     * Checks a plain password against a stored hash. A null hash (unknown account or social-only account) still costs
     * one hash comparison, so response time does not reveal whether the account exists.
     */
    public function check(string $plain, ?string $hash): bool;
}
