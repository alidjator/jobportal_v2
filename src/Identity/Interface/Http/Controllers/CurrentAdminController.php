<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Identity\Application\Account\GetCurrentAdmin;
use Src\Identity\Interface\Http\Resources\CurrentAdminResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class CurrentAdminController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetCurrentAdmin $getCurrentAdmin): CurrentAdminResource
    {
        return new CurrentAdminResource($getCurrentAdmin->execute($actors->fromRequest($request)));
    }
}
