<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

class ProfessionRecord extends TranslatableRecord
{
    protected $table = 'professions';

    protected function translationTable(): string
    {
        return 'profession_translations';
    }

    protected function translationKey(): string
    {
        return 'profession_id';
    }
}
