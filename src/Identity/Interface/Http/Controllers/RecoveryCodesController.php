<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Identity\Application\TwoFactor\RegenerateRecoveryCodes;
use Src\Identity\Interface\Http\Requests\RegenerateRecoveryCodesRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class RecoveryCodesController extends ApiController
{
    public function __invoke(RegenerateRecoveryCodesRequest $request, ActorResolver $actors, RegenerateRecoveryCodes $regenerate): JsonResponse
    {
        $codes = $regenerate->execute($actors->fromRequest($request), $request->code(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => ['recovery_codes' => $codes]]);
    }
}
