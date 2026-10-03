<?php

namespace Src\ReferenceData\Infrastructure;

use Src\ReferenceData\Domain\ReferenceDataRepository;
use Src\ReferenceData\Infrastructure\Persistence\EloquentReferenceDataRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

class ReferenceDataServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        ReferenceDataRepository::class => EloquentReferenceDataRepository::class,
    ];
}
