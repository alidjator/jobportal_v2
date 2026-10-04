<?php

namespace Src\Notification\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Notification\Application\DeviceTokens\RegisterDeviceToken;
use Src\Notification\Application\DeviceTokens\UnregisterDeviceToken;
use Src\Notification\Interface\Http\Requests\RegisterDeviceTokenRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The push tokens of the signed-in user's devices. The token itself is never returned: clients only register and remove. */
final class DeviceTokenController extends ApiController
{
    public function store(RegisterDeviceTokenRequest $request, ActorResolver $actors, RegisterDeviceToken $register): Response
    {
        $register->execute($actors->fromRequest($request), $request->token(), $request->platform());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function destroy(Request $request, string $token, ActorResolver $actors, UnregisterDeviceToken $unregister): Response
    {
        $unregister->execute($actors->fromRequest($request), $token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
