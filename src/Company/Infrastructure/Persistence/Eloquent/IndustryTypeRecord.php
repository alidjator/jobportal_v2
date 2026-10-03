<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

class IndustryTypeRecord extends TranslatableRecord
{
    protected $table = 'industry_types';

    protected function translationTable(): string
    {
        return 'industry_type_translations';
    }

    protected function translationKey(): string
    {
        return 'industry_type_id';
    }
}
