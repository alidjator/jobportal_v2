<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\Auth\SignOutEverywhere;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Serves both account kinds: the guard of the route decides whose tokens are revoked. */
final class SignOutEverywhereController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, SignOutEverywhere $signOutEverywhere): Response
    {
        $signOutEverywhere->execute($actors->fromRequest($request), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
