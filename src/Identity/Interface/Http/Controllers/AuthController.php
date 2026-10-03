<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Identity\Application\Commands\Login;
use Src\Identity\Application\Commands\Register;
use Src\Identity\Application\Commands\ResetPassword;
use Src\Identity\Application\Commands\SendResetCode;
use Src\Identity\Application\Commands\SocialAuthenticate;
use Src\Identity\Application\Queries\GetAccount;
use Src\Identity\Application\Queries\GetUserInfo;
use Src\Identity\Domain\Exceptions\SocialAuthFailed;
use Src\Identity\Interface\Http\Requests\LoginRequest;
use Src\Identity\Interface\Http\Requests\RegisterRequest;
use Src\Identity\Interface\Http\Requests\ResetPasswordRequest;
use Src\Identity\Interface\Http\Requests\SendResetCodeRequest;
use Src\Identity\Interface\Http\Requests\SocialAuthRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class AuthController extends ApiController
{
    public function login(LoginRequest $request, Login $login): JsonResponse
    {
        $result = $login($request->string('email')->toString(), $request->string('password')->toString());

        return $result === null
            ? ApiResponse::unauthenticated('Invalid Credentials')
            : ApiResponse::success($result);
    }

    public function register(RegisterRequest $request, Register $register): JsonResponse
    {
        $user = $register(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('role'),
        );

        return ApiResponse::raw(['data' => $user, 'message' => 'Registration Succeeded']);
    }

    public function profile(Request $request, GetAccount $account): JsonResponse
    {
        return ApiResponse::success($account($request->user()->getKey()));
    }

    /** `GET /user`: baris user apa adanya (tanpa pembungkus `data`), seperti aplikasi lama. */
    public function user(Request $request, GetAccount $account): JsonResponse
    {
        return ApiResponse::raw($account($request->user()->getKey()));
    }

    public function userInfo(Request $request, GetUserInfo $info): JsonResponse
    {
        $user = $request->user('sanctum');
        $result = $user === null ? null : $info($user->getKey(), (string) $request->bearerToken());

        return $result === null
            ? ApiResponse::unauthenticated('Unauthenticated User')
            : ApiResponse::success($result);
    }

    public function sendResetCodeEmail(SendResetCodeRequest $request, SendResetCode $sendResetCode): JsonResponse
    {
        $code = $sendResetCode($request->string('email')->toString());

        $data = ['message' => 'We have emailed you password reset code'];

        // Kode hanya diekspos untuk keperluan testing, jangan pernah di production.
        if (app()->environment('local', 'testing')) {
            $data['code'] = (int) $code;
        }

        return ApiResponse::success($data);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success(['message' => 'Password reset successfully']);
    }

    public function socialAuthentication(SocialAuthRequest $request, SocialAuthenticate $authenticate): JsonResponse
    {
        try {
            $result = $authenticate(
                $request->string('provider')->toString(),
                $request->string('firebaseToken')->toString(),
                $request->string('actionKey')->toString(),
                $request->has('role') ? $request->string('role')->toString() : null,
            );
        } catch (SocialAuthFailed $e) {
            return ApiResponse::raw(['data' => ['message' => $e->getMessage()]], 401);
        }

        return ApiResponse::raw(['data' => $result->data], $result->status);
    }
}
