<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ContactInfoRecord extends Model
{
    protected $table = 'contact_infos';

    protected $guarded = [];
}
