<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Assessment\Application\Invitations\InvitePsychologistCommand;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Application\RequestContext;

final class InvitePsychologistRequest extends PsychologistCredentialsRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'role' => ['sometimes', 'string', Rule::enum(PsychologistRole::class)],
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
            $this->has('role') ? PsychologistRole::from($this->string('role')->toString()) : PsychologistRole::Member,
            $context->language,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
