<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Identity\Application\Account\GetCurrentUser;
use Src\Identity\Interface\Http\Resources\CurrentUserResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class CurrentUserController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetCurrentUser $getCurrentUser): CurrentUserResource
    {
        return new CurrentUserResource($getCurrentUser->execute($actors->fromRequest($request)));
    }
}
