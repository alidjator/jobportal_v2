<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\Users\CorrectUserEmail;
use Src\Identity\Application\Users\ResendUserVerification;
use Src\Identity\Application\Users\ResetUserTwoFactorByAdmin;
use Src\Identity\Application\Users\SendUserActivationEmail;
use Src\Identity\Application\Users\SendUserPasswordReset;
use Src\Identity\Interface\Http\Requests\AdminAccountActionRequest;
use Src\Identity\Interface\Http\Requests\CorrectUserEmailRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** What an admin holding `users.manage` does for a job seeker or company member who is locked out: correct the email, reset the second factor, and send the activation email, a password reset code or a verification code again. */
final class AdminUserActionController extends ApiController
{
    public function correctEmail(CorrectUserEmailRequest $request, int $id, ActorResolver $actors, CorrectUserEmail $correct): Response
    {
        $correct->execute($actors->fromRequest($request), $id, $request->email(), $request->reason(), $request->identityCheck(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function resetTwoFactor(AdminAccountActionRequest $request, int $id, ActorResolver $actors, ResetUserTwoFactorByAdmin $reset): Response
    {
        $reset->execute($actors->fromRequest($request), $id, $request->reason(), $request->identityCheck(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function activationEmail(Request $request, int $id, ActorResolver $actors, SendUserActivationEmail $send): Response
    {
        $send->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function passwordReset(Request $request, int $id, ActorResolver $actors, SendUserPasswordReset $send): Response
    {
        $send->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function resendVerification(Request $request, int $id, ActorResolver $actors, ResendUserVerification $resend): Response
    {
        $resend->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
