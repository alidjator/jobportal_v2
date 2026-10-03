<?php

namespace Src\Identity\Application\Queries;

use Src\Identity\Domain\UserRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Baris user (untuk `/profile` dan `/user`). */
final class GetAccount
{
    public function __construct(private readonly UserRepository $users) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        return $this->users->findById($userId) ?? throw new EntityNotFound('Not Found');
    }
}
