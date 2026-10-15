<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** An edit of a legal entity: only the fields sent change (see `LegalEntityFields`); at least one is needed. */
final class UpdateLegalEntityRequest extends FormRequest
{
    use LegalEntityFields;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->legalEntityRules(required: false);
    }

    /** @return array<string, mixed> */
    public function changes(): array
    {
        return $this->legalEntityChanges();
    }
}
