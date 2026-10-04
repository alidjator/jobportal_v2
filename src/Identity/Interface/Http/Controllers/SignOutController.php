<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Application\Auth\SignOut;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Serves both account kinds: the guard of the route decides whose token is revoked. */
final class SignOutController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, SignOut $signOut): Response
    {
        $account = $request->user();
        $token = $account instanceof HasApiTokens ? $account->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        $signOut->execute($actors->fromRequest($request), (int) $token->getKey());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
