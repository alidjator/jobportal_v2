<?php

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\TransactionManager;

final readonly class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }
}
