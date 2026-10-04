<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Identity\Application\Auth\ResetPassword;
use Src\Identity\Interface\Http\Requests\ResetPasswordRequest;
use Src\Shared\Interface\Http\ApiController;

final class ResetPasswordController extends ApiController
{
    public function __invoke(ResetPasswordRequest $request, ResetPassword $resetPassword): Response
    {
        $resetPassword->execute($request->email(), $request->code(), $request->newPassword(), $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
