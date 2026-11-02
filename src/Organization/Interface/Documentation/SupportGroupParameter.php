<?php

namespace Src\Organization\Interface\Documentation;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\RouteInfo;
use Src\Organization\Interface\Http\Middleware\ActAsSupport;

/**
 * The support reads reuse the controllers of the group member's reads, so Scramble cannot see the `group_id` query parameter that `ActAsSupport` requires.
 * This adds it to every route behind that middleware.
 */
final class SupportGroupParameter
{
    public static function document(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array(ActAsSupport::class, $routeInfo->route->gatherMiddleware(), true)) {
            return;
        }

        $operation->addParameters([
            (new Parameter('group_id', 'query'))
                ->required(true)
                ->description('The group whose support access the platform admin reads under; its grant must be active.')
                ->setSchema(Schema::fromType((new IntegerType)->setMin(1))),
        ]);
    }
}
