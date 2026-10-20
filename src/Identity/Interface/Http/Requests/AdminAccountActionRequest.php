<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Why the admin acts and which two proofs of identity were seen (`docs/design/phase-8-activation.md` Appendix B). */
final class AdminAccountActionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'identity_check' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }

    public function identityCheck(): string
    {
        return trim($this->string('identity_check')->toString());
    }
}
