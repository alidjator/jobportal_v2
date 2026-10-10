<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Identity\Application\Auth\RequestPasswordReset;
use Src\Identity\Interface\Http\Requests\ForgotPasswordRequest;
use Src\Shared\Interface\Http\ApiController;

final class ForgotPasswordController extends ApiController
{
    public function __invoke(ForgotPasswordRequest $request, RequestPasswordReset $requestReset): Response
    {
        $requestReset->execute($request->email(), $request->ip());

        return response()->noContent();
    }
}
