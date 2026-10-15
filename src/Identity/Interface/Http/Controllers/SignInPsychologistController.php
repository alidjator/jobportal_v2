<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Auth\SignInPsychologist;
use Src\Identity\Interface\Http\Requests\SignInRequest;
use Src\Identity\Interface\Http\Resources\TwoFactorChallengeResource;
use Src\Shared\Interface\Http\ApiController;

/** The first step: a right password answers a challenge, never a token. The client finishes at `POST /provider/auth/two-factor`. */
final class SignInPsychologistController extends ApiController
{
    public function __invoke(SignInRequest $request, SignInPsychologist $signIn): TwoFactorChallengeResource
    {
        return new TwoFactorChallengeResource($signIn->execute($request->toCommand()));
    }
}
