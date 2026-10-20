<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Application\EmailChange\CancelEmailChange;
use Src\Identity\Application\EmailChange\ConfirmEmailChange;
use Src\Identity\Application\EmailChange\RequestEmailChange;
use Src\Identity\Interface\Http\Requests\ConfirmEmailChangeRequest;
use Src\Identity\Interface\Http\Requests\RequestEmailChangeRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The self-service change of the sign-in email: ask for a code, confirm it, or cancel. The answer to the request is 202 whether or not the address belongs to another account. */
final class EmailChangeController extends ApiController
{
    public function store(RequestEmailChangeRequest $request, ActorResolver $actors, RequestEmailChange $requestChange): Response
    {
        $requestChange->execute($actors->fromRequest($request), $request->email());

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function confirm(ConfirmEmailChangeRequest $request, ActorResolver $actors, ConfirmEmailChange $confirm): Response
    {
        $account = $request->user();
        $token = $account instanceof HasApiTokens ? $account->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        $confirm->execute(
            $actors->fromRequest($request), (int) $token->getKey(), $request->code(), $request->password(), $request->oldCode(), $request->ip(), $request->userAgent(),
        );

        return response()->noContent();
    }

    public function destroy(Request $request, ActorResolver $actors, CancelEmailChange $cancel): Response
    {
        $cancel->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
