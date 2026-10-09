<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Switch a CV template on or off and/or make it the default; at least one of the two is given. */
final class UpdateCvTemplateRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required_without:is_default', 'boolean'],
            'is_default' => ['required_without:enabled', 'boolean'],
        ];
    }

    public function enabled(): ?bool
    {
        return $this->has('enabled') ? $this->boolean('enabled') : null;
    }

    public function isDefault(): ?bool
    {
        return $this->has('is_default') ? $this->boolean('is_default') : null;
    }
}
