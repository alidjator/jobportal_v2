<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

class TeamSizeRecord extends TranslatableRecord
{
    protected $table = 'team_sizes';

    protected function translationTable(): string
    {
        return 'team_size_translations';
    }

    protected function translationKey(): string
    {
        return 'team_size_id';
    }
}
