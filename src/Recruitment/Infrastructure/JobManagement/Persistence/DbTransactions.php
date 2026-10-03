<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\Transactions;

class DbTransactions implements Transactions
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
