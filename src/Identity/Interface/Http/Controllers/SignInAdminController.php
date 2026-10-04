<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\Auth\SignInAdmin;
use Src\Identity\Interface\Http\Requests\SignInRequest;
use Src\Identity\Interface\Http\Resources\AdminResource;
use Src\Shared\Interface\Http\ApiController;

final class SignInAdminController extends ApiController
{
    public function __invoke(SignInRequest $request, SignInAdmin $signIn): JsonResponse
    {
        $result = $signIn->execute($request->toCommand());

        return new JsonResponse(['data' => [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt->format(DateTimeInterface::ATOM),
            'admin' => (new AdminResource($result->admin))->resolve($request),
        ]]);
    }
}
