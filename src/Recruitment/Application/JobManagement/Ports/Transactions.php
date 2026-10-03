<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

interface Transactions
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
