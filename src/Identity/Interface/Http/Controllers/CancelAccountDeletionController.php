<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Account\CancelAccountDeletion;
use Src\Identity\Interface\Http\Requests\CancelAccountDeletionRequest;
use Src\Identity\Interface\Http\Resources\UserSignInResource;
use Src\Shared\Interface\Http\ApiController;

final class CancelAccountDeletionController extends ApiController
{
    public function __invoke(CancelAccountDeletionRequest $request, CancelAccountDeletion $cancelDeletion): UserSignInResource
    {
        return new UserSignInResource($cancelDeletion->execute($request->toCommand()));
    }
}
