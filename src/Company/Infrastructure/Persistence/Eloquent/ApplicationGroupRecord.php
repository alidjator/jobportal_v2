<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ApplicationGroupRecord extends Model
{
    protected $table = 'application_groups';

    protected $guarded = [];
}
