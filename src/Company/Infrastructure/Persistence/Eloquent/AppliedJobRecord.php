<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AppliedJobRecord extends Model
{
    protected $table = 'applied_jobs';

    protected $guarded = [];
}
