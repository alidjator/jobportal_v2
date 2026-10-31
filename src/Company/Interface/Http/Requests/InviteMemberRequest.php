<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InviteMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'preset' => ['required', 'string', 'max:40'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    /** The built-in preset (`recruiter`) or saved preset (`saved_12`) that sets the role and permissions. */
    public function preset(): string
    {
        return $this->string('preset')->toString();
    }
}
