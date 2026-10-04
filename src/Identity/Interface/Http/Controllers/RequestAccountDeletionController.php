<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\Account\RequestAccountDeletion;
use Src\Identity\Interface\Http\Requests\RequestAccountDeletionRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class RequestAccountDeletionController extends ApiController
{
    public function __invoke(RequestAccountDeletionRequest $request, ActorResolver $actors, RequestAccountDeletion $requestDeletion): JsonResponse
    {
        $scheduledFor = $requestDeletion->execute($actors->fromRequest($request), $request->password(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => ['scheduled_for' => $scheduledFor->format(DateTimeInterface::ATOM)]]);
    }
}
