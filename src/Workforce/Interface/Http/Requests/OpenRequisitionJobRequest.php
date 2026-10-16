<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The job opened for a requisition: an optional `title` (the position's by default) and `vacancies` (the hires not yet opened by default). */
final class OpenRequisitionJobRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:150'],
            'vacancies' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function title(): ?string
    {
        return $this->has('title') ? trim($this->string('title')->toString()) : null;
    }

    public function vacancies(): ?int
    {
        return $this->has('vacancies') ? $this->integer('vacancies') : null;
    }
}
