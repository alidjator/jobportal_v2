<?php

namespace Src\ReferenceData\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'languages';

    protected $guarded = [];
}
