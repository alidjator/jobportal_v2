<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Application\Auth\ChangePassword;
use Src\Identity\Interface\Http\Requests\ChangePasswordRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Serves both account kinds: the guard of the route decides whose password changes. The token of the request stays valid. */
final class ChangePasswordController extends ApiController
{
    public function __invoke(ChangePasswordRequest $request, ActorResolver $actors, ChangePassword $changePassword): Response
    {
        $account = $request->user();
        $token = $account instanceof HasApiTokens ? $account->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        $changePassword->execute(
            $actors->fromRequest($request), (int) $token->getKey(),
            $request->currentPassword(), $request->newPassword(), $request->ip(), $request->userAgent(),
        );

        return response()->noContent();
    }
}
