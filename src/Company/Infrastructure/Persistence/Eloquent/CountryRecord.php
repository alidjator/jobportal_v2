<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CountryRecord extends Model
{
    protected $table = 'countries';

    protected $guarded = [];
}
