<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\TwoFactor\BeginMyTwoFactorEnrolment;
use Src\Identity\Application\TwoFactor\ConfirmMyTwoFactor;
use Src\Identity\Application\TwoFactor\DisableMyTwoFactor;
use Src\Identity\Application\TwoFactor\GetMyTwoFactor;
use Src\Identity\Application\TwoFactor\RegenerateMyRecoveryCodes;
use Src\Identity\Interface\Http\Requests\DisableTwoFactorRequest;
use Src\Identity\Interface\Http\Requests\RegenerateRecoveryCodesRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The second factor of the signed-in company member (the use cases refuse any other account). */
final class MyTwoFactorController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetMyTwoFactor $get): JsonResponse
    {
        $status = $get->execute($actors->fromRequest($request));

        return new JsonResponse(['data' => ['enabled' => $status->enabled, 'required' => $status->required, 'recovery_codes_left' => $status->recoveryCodesLeft]]);
    }

    public function store(Request $request, ActorResolver $actors, BeginMyTwoFactorEnrolment $begin): JsonResponse
    {
        $enrolment = $begin->execute($actors->fromRequest($request));

        return new JsonResponse(['data' => ['secret' => $enrolment->secret, 'otpauth_url' => $enrolment->otpauthUrl, 'qr_code' => $enrolment->qrCode]]);
    }

    public function confirm(RegenerateRecoveryCodesRequest $request, ActorResolver $actors, ConfirmMyTwoFactor $confirm): JsonResponse
    {
        $codes = $confirm->execute($actors->fromRequest($request), $request->code(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => ['recovery_codes' => $codes]]);
    }

    public function destroy(DisableTwoFactorRequest $request, ActorResolver $actors, DisableMyTwoFactor $disable): Response
    {
        $disable->execute($actors->fromRequest($request), $request->password(), $request->code(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function recoveryCodes(RegenerateRecoveryCodesRequest $request, ActorResolver $actors, RegenerateMyRecoveryCodes $regenerate): JsonResponse
    {
        $codes = $regenerate->execute($actors->fromRequest($request), $request->code(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => ['recovery_codes' => $codes]]);
    }
}
