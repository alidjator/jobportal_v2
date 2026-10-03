<?php

namespace Src\Identity\Application\Commands;

use Src\Identity\Application\AuthPayload;
use Src\Identity\Domain\AccessTokens;
use Src\Identity\Domain\UserRepository;

final class Login
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccessTokens $tokens,
        private readonly AuthPayload $payload,
    ) {}

    /** @return array<string,mixed>|null null bila kredensial salah */
    public function __invoke(string $email, string $password): ?array
    {
        $user = $this->users->findByCredentials($email, $password);

        if ($user === null) {
            return null;
        }

        return $this->payload->build($user, $this->tokens->issue($user['id']), 'Login Succeeded');
    }
}
