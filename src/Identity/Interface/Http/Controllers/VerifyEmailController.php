<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Identity\Application\Auth\VerifyEmail;
use Src\Identity\Interface\Http\Requests\VerifyEmailRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class VerifyEmailController extends ApiController
{
    public function __invoke(VerifyEmailRequest $request, ActorResolver $actors, VerifyEmail $verifyEmail): Response
    {
        $verifyEmail->execute($actors->fromRequest($request), $request->code());

        return response()->noContent();
    }
}
