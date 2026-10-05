<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Application\CreateCompanyCommand;
use Src\Shared\Application\RequestContext;

final class CreateCompanyRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'industry_id' => ['required', 'integer', 'min:1'],
            'organization_type_id' => ['required', 'integer', 'min:1'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function toCommand(RequestContext $context): CreateCompanyCommand
    {
        return new CreateCompanyCommand(
            trim($this->string('name')->toString()),
            $this->integer('industry_id'),
            $this->integer('organization_type_id'),
            $this->string('admin_email')->toString(),
            $context->language,
            $this->ip(),
            $this->userAgent(),
        );
    }
}
