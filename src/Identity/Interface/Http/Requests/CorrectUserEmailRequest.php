<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The new address, why it is corrected and which two proofs of identity were seen (`docs/design/phase-8-activation.md` Appendix B). */
final class CorrectUserEmailRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'identity_check' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
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
