<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

class OrganizationTypeRecord extends TranslatableRecord
{
    protected $table = 'organization_types';

    protected function translationTable(): string
    {
        return 'organization_type_translations';
    }

    protected function translationKey(): string
    {
        return 'organization_type_id';
    }
}
