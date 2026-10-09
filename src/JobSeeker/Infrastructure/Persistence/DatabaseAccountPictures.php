<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Application\Cv\AccountPictures;

/** Reads the picture path straight from `identity_users`, as the candidate search does for its list rows. */
final readonly class DatabaseAccountPictures implements AccountPictures
{
    public function __construct(private ConnectionInterface $connection) {}

    public function pathOf(int $userId): ?string
    {
        $path = $this->connection->table('identity_users')->where('id', $userId)->value('avatar_path');

        return is_string($path) && $path !== '' ? $path : null;
    }
}
