<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\TwoFactor\CompleteMemberSignIn;
use Src\Identity\Interface\Http\Requests\UserTwoFactorSignInRequest;
use Src\Identity\Interface\Http\Resources\UserResource;
use Src\Shared\Interface\Http\ApiController;

/** The second step of a company member's sign-in: the challenge plus a code (or a recovery code) answers the usual token, and the recovery codes once at enrolment. */
final class MemberTwoFactorSignInController extends ApiController
{
    public function __invoke(UserTwoFactorSignInRequest $request, CompleteMemberSignIn $complete): JsonResponse
    {
        $result = $complete->execute($request->toCommand());

        $data = [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt->format(DateTimeInterface::ATOM),
            'user' => (new UserResource($result->user))->resolve($request),
        ];

        if ($result->recoveryCodes !== []) {
            $data['recovery_codes'] = $result->recoveryCodes;
        }

        return new JsonResponse(['data' => $data]);
    }
}
