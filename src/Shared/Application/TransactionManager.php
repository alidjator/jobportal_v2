<?php

namespace Src\Shared\Application;

/**
 * Runs a use case's changes as one unit of work. Repositories never open their own transaction, and read-only use
 * cases do not use this.
 */
interface TransactionManager
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(callable $callback): mixed;
}
