<?php

namespace Src\Platform\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Shared\Domain\TranslatedText;

/**
 * Save (`PUT`) or preview (`POST .../preview`) an email template in one language. The language is in the URL for saving and in the
 * body for previewing. Variable rules are checked against the template by the use case. For a preview, subject and body are optional drafts.
 */
final class EmailTemplateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isPreview = $this->isMethod('POST');

        return [
            'locale' => $isPreview ? ['required', 'string', Rule::in(TranslatedText::LANGUAGES)] : ['prohibited'],
            'subject' => [$isPreview ? 'nullable' : 'required', 'string', 'max:1000'],
            'body' => [$isPreview ? 'nullable' : 'required', 'string', 'max:100000'],
        ];
    }

    public function locale(): string
    {
        return $this->isMethod('POST') ? $this->string('locale')->toString() : (string) $this->route('locale');
    }

    public function subject(): ?string
    {
        return $this->has('subject') && $this->input('subject') !== null ? $this->string('subject')->toString() : null;
    }

    public function body(): ?string
    {
        return $this->has('body') && $this->input('body') !== null ? $this->string('body')->toString() : null;
    }
}
