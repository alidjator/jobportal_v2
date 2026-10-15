<?php

namespace Src\Shared\Infrastructure;

use Illuminate\Contracts\Container\Container;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataExporters;

/** The exporters are the services each context tags with `PersonalDataExporter::class` in its provider. */
final readonly class ContainerPersonalDataExporters implements PersonalDataExporters
{
    public function __construct(private Container $container) {}

    public function all(): array
    {
        return iterator_to_array($this->container->tagged(PersonalDataExporter::class), false);
    }
}
