<?php

namespace Src\Assessment\Interface\Http\Requests;

use Src\Assessment\Application\Invitations\InvitePsychologistCommand;
use Src\Shared\Application\RequestContext;

final class InvitePsychologistRequest extends PsychologistCredentialsRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            ...$this->credentialRules(),
        ];
    }

    public function toCommand(int $providerId, RequestContext $context): InvitePsychologistCommand
    {
        return new InvitePsychologistCommand(
            $providerId,
            $this->string('email')->toString(),
            $this->string('name')->toString(),
            $this->strNumber(),
            $this->strValidUntil(),
            $this->silpNumber(),
            $this->silpValidUntil(),
            $context->language,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
