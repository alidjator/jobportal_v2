<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Group\LegalEntityInput;

/** A new legal entity of the group (see `LegalEntityFields`). */
final class LegalEntityRequest extends FormRequest
{
    use LegalEntityFields;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->legalEntityRules();
    }

    public function entity(): LegalEntityInput
    {
        return $this->legalEntityInput();
    }
}
