<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialAuthRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'firebaseToken' => 'required',
            'provider' => 'required|in:google,facebook',
            'actionKey' => 'required|in:login,registration',
            'role' => 'sometimes|in:candidate,company',
        ];
    }
}
