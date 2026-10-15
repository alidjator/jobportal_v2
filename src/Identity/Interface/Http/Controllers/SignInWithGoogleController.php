<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Auth\GoogleSignInResult;
use Src\Identity\Application\Auth\SignInWithGoogle;
use Src\Identity\Interface\Http\Requests\GoogleSignInRequest;
use Src\Identity\Interface\Http\Resources\GoogleSignInResource;
use Src\Identity\Interface\Http\Resources\TwoFactorChallengeResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

final class SignInWithGoogleController extends ApiController
{
    public function __invoke(GoogleSignInRequest $request, RequestContext $context, SignInWithGoogle $signIn): GoogleSignInResource|TwoFactorChallengeResource
    {
        $outcome = $signIn->execute($request->toCommand($context));

        return $outcome instanceof GoogleSignInResult ? new GoogleSignInResource($outcome) : new TwoFactorChallengeResource($outcome);
    }
}
