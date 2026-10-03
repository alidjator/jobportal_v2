<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class BookmarkCategoryRecord extends Model
{
    protected $table = 'company_bookmark_categories';

    protected $guarded = [];
}
