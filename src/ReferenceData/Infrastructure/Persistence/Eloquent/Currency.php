<?php

namespace Src\ReferenceData\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $guarded = [];
}
