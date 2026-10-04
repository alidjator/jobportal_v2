<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Auth\SignInUser;
use Src\Identity\Interface\Http\Requests\SignInRequest;
use Src\Identity\Interface\Http\Resources\UserSignInResource;
use Src\Shared\Interface\Http\ApiController;

final class SignInUserController extends ApiController
{
    public function __invoke(SignInRequest $request, SignInUser $signIn): UserSignInResource
    {
        return new UserSignInResource($signIn->execute($request->toCommand()));
    }
}
