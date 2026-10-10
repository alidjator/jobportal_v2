<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\Auth\ResendEmailVerificationCode;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class ResendEmailVerificationCodeController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, ResendEmailVerificationCode $resend): Response
    {
        $resend->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
