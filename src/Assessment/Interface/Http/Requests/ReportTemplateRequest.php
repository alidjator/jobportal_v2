<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The report template of a battery. The placeholders are checked by the use case. */
final class ReportTemplateRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:30000']];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
