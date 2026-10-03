<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class SocialLinkRecord extends Model
{
    protected $table = 'social_links';

    protected $guarded = [];
}
