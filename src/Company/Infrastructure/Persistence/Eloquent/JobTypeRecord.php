<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

class JobTypeRecord extends TranslatableRecord
{
    protected $table = 'job_types';

    protected function translationTable(): string
    {
        return 'job_type_translations';
    }

    protected function translationKey(): string
    {
        return 'job_type_id';
    }
}
