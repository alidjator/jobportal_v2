<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\TwoFactor\CompleteAdminSignIn;
use Src\Identity\Interface\Http\Requests\TwoFactorSignInRequest;
use Src\Identity\Interface\Http\Resources\AdminResource;
use Src\Shared\Interface\Http\ApiController;

final class TwoFactorSignInController extends ApiController
{
    public function __invoke(TwoFactorSignInRequest $request, CompleteAdminSignIn $complete): JsonResponse
    {
        $result = $complete->execute($request->toCommand());

        $data = [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt->format(DateTimeInterface::ATOM),
            'admin' => (new AdminResource($result->admin))->resolve($request),
        ];

        if ($result->recoveryCodes !== []) {
            $data['recovery_codes'] = $result->recoveryCodes;
        }

        return new JsonResponse(['data' => $data]);
    }
}
