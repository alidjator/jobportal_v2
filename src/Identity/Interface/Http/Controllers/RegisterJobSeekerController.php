<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Identity\Application\Auth\RegisterJobSeeker;
use Src\Identity\Interface\Http\Requests\RegisterJobSeekerRequest;
use Src\Identity\Interface\Http\Resources\UserSignInResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

final class RegisterJobSeekerController extends ApiController
{
    public function __invoke(RegisterJobSeekerRequest $request, RequestContext $context, RegisterJobSeeker $register): JsonResponse
    {
        return (new UserSignInResource($register->execute($request->toCommand($context))))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
