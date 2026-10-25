<?php

namespace Src\Platform\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Platform\Application\Portal\PortalIdentity;

/** The identity of the career site as a draft. Which companies and scopes may go together is checked when the draft is published. */
final class PortalIdentityRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:marketplace,company'],
            'scope' => ['nullable', 'string', 'in:group,company'],
            'company_ids' => ['present', 'array', 'max:50'],
            'company_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function identity(): PortalIdentity
    {
        return PortalIdentity::fromArray($this->validated());
    }
}
