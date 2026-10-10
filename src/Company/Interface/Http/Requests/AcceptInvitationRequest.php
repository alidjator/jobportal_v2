<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Application\Invitations\AcceptInvitationCommand;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\Rules\NewPassword;

final class AcceptInvitationRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api';

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => NewPassword::rules(),
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toCommand(string $token, RequestContext $context): AcceptInvitationCommand
    {
        return new AcceptInvitationCommand(
            $token,
            trim($this->string('name')->toString()),
            $this->string('password')->toString(),
            $context->language,
            $this->string('device_name', self::DEFAULT_DEVICE_NAME)->toString() ?: self::DEFAULT_DEVICE_NAME,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
