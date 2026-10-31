<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'preset' => ['sometimes', 'string', 'max:40'],
        ];
    }

    public function name(): ?string
    {
        return $this->has('name') ? $this->string('name')->toString() : null;
    }

    /** The built-in preset (`recruiter`) or saved preset (`saved_12`) that sets the role and permissions; null leaves them as they are. */
    public function preset(): ?string
    {
        return $this->has('preset') ? $this->string('preset')->toString() : null;
    }
}
