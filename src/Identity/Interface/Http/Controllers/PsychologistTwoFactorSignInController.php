<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\TwoFactor\CompletePsychologistSignIn;
use Src\Identity\Interface\Http\Requests\UserTwoFactorSignInRequest;
use Src\Identity\Interface\Http\Resources\UserResource;
use Src\Shared\Interface\Http\ApiController;

final class PsychologistTwoFactorSignInController extends ApiController
{
    public function __invoke(UserTwoFactorSignInRequest $request, CompletePsychologistSignIn $complete): JsonResponse
    {
        $result = $complete->execute($request->toCommand());

        $data = [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt->format(DateTimeInterface::ATOM),
            'user' => (new UserResource($result->user))->resolve($request),
        ];

        if ($result->recoveryCodes !== []) {
            $data['recovery_codes'] = $result->recoveryCodes;
        }

        return new JsonResponse(['data' => $data]);
    }
}
