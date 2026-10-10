<?php

namespace Src\Newsletter\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Newsletter\Application\ConfirmSubscription;
use Src\Newsletter\Application\Subscribe;
use Src\Newsletter\Application\Unsubscribe;
use Src\Newsletter\Interface\Http\Requests\SubscribeRequest;
use Src\Newsletter\Interface\Http\Requests\TokenRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\RequestLanguage;

/** What a visitor does with the list: ask to join, confirm, leave. Nothing here says whether an address is on the list. */
final class SubscriptionController extends ApiController
{
    public function subscribe(SubscribeRequest $request, Subscribe $subscribe): Response
    {
        $subscribe->execute($request->email(), RequestLanguage::of($request));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    public function confirm(TokenRequest $request, ConfirmSubscription $confirm): Response
    {
        $confirm->execute($request->token());

        return response()->noContent();
    }

    public function unsubscribe(TokenRequest $request, Unsubscribe $unsubscribe): Response
    {
        $unsubscribe->execute($request->token());

        return response()->noContent();
    }

    /** The one-click form of the mail programs: the token is in the address, the body is ignored. */
    public function unsubscribeOneClick(string $token, Unsubscribe $unsubscribe): Response
    {
        $unsubscribe->execute($token);

        return response()->noContent();
    }
}
