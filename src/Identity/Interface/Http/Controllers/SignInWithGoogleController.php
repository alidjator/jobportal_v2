<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Auth\SignInWithGoogle;
use Src\Identity\Interface\Http\Requests\GoogleSignInRequest;
use Src\Identity\Interface\Http\Resources\GoogleSignInResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

final class SignInWithGoogleController extends ApiController
{
    public function __invoke(GoogleSignInRequest $request, RequestContext $context, SignInWithGoogle $signIn): GoogleSignInResource
    {
        return new GoogleSignInResource($signIn->execute($request->toCommand($context)));
    }
}
