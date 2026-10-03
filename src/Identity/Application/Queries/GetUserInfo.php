<?php

namespace Src\Identity\Application\Queries;

use Src\Identity\Application\AuthPayload;
use Src\Identity\Domain\UserRepository;

final class GetUserInfo
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthPayload $payload,
    ) {}

    /** @return array<string,mixed>|null */
    public function __invoke(int $userId, string $bearerToken): ?array
    {
        $user = $this->users->findById($userId);

        return $user === null ? null : $this->payload->build($user, $bearerToken, 'User data retrieved successfully');
    }
}
