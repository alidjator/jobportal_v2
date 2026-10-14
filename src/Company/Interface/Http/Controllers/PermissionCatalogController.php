<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Company\Application\GetPermissionCatalog;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class PermissionCatalogController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetPermissionCatalog $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->execute($actors->fromRequest($request))]);
    }
}
