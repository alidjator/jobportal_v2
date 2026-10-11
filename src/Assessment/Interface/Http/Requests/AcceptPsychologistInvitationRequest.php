<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Invitations\AcceptInvitationCommand;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\Rules\NewPassword;

final class AcceptPsychologistInvitationRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['password' => NewPassword::rules()];
    }

    public function toCommand(string $token, RequestContext $context): AcceptInvitationCommand
    {
        return new AcceptInvitationCommand($token, $this->string('password')->toString(), $context->language, $this->ip(), $this->userAgent());
    }
}
