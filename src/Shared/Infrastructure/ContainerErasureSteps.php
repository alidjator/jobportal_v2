<?php

namespace Src\Shared\Infrastructure;

use Illuminate\Contracts\Container\Container;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Accounts\ErasureSteps;

/** The steps are the services each context tags with `ErasureStep::class` in its provider. */
final readonly class ContainerErasureSteps implements ErasureSteps
{
    public function __construct(private Container $container) {}

    public function all(): array
    {
        return iterator_to_array($this->container->tagged(ErasureStep::class), false);
    }
}
