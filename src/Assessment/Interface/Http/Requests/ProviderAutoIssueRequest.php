<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The platform admin's gate for the automatic issue of reports of a provider. */
final class ProviderAutoIssueRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['allowed' => ['required', 'boolean']];
    }

    public function allowed(): bool
    {
        return $this->boolean('allowed');
    }
}
