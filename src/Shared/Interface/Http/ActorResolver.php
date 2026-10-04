<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorProvider;

/** Builds the `Actor` of a use case from the account authenticated on the request. */
final class ActorResolver
{
    /** @throws AuthenticationException when the request carries no account that can act */
    public function fromRequest(Request $request): Actor
    {
        $account = $request->user();

        if (! $account instanceof ActorProvider) {
            throw new AuthenticationException;
        }

        return $account->toActor();
    }
}
