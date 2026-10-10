<?php

namespace Src\Identity\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Src\Identity\Application\Auth\SignInAdmin;
use Src\Identity\Interface\Http\Requests\SignInRequest;
use Src\Shared\Interface\Http\ApiController;

/** The first step: a right password answers a challenge, never a token. The client finishes at `POST /admin/auth/two-factor`. */
final class SignInAdminController extends ApiController
{
    public function __invoke(SignInRequest $request, SignInAdmin $signIn): JsonResponse
    {
        $challenge = $signIn->execute($request->toCommand());

        $data = [
            'status' => $challenge->enrolment === null ? 'two_factor_required' : 'two_factor_enrolment_required',
            'challenge_token' => $challenge->token,
            'expires_at' => $challenge->expiresAt->format(DateTimeInterface::ATOM),
        ];

        if ($challenge->enrolment !== null) {
            $data['enrolment'] = [
                'secret' => $challenge->enrolment->secret,
                'otpauth_url' => $challenge->enrolment->otpauthUrl,
                'qr_code' => $challenge->enrolment->qrCode,
            ];
        }

        return new JsonResponse(['data' => $data]);
    }
}
