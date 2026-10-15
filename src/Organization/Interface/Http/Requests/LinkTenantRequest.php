<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The company to link to a legal entity. */
final class LinkTenantRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1']];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }
}
