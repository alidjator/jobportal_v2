<?php

namespace Src\ReferenceData\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

    protected $guarded = [];
}
