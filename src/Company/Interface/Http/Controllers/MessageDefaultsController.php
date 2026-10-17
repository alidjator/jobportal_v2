<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Company\Application\Messages\GetMessageDefaults;
use Src\Company\Application\Messages\UpdateMessageDefaults;
use Src\Company\Interface\Http\Requests\UpdateMessageDefaultsRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The default texts the company's message dialogs start with. */
final class MessageDefaultsController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetMessageDefaults $get): JsonResponse
    {
        return response()->json(['data' => $get->execute($actors->fromRequest($request))->toArray()]);
    }

    public function update(UpdateMessageDefaultsRequest $request, ActorResolver $actors, UpdateMessageDefaults $update): JsonResponse
    {
        return response()->json(['data' => $update->execute($actors->fromRequest($request), $request->changes(), $request->ip(), $request->userAgent())->toArray()]);
    }
}
