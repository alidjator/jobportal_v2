<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body of creating and of changing a test. The content limits are checked by the use case. */
final class TestRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:6000'],
            'instructions' => ['nullable', 'string', 'max:6000'],
            'measured_capabilities' => ['nullable', 'array', 'max:50'],
            'measured_capabilities.*' => ['string', 'max:200'],
        ];
    }

    public function title(): string
    {
        return $this->string('title')->toString();
    }

    public function description(): ?string
    {
        return $this->filled('description') ? $this->string('description')->toString() : null;
    }

    public function instructions(): ?string
    {
        return $this->filled('instructions') ? $this->string('instructions')->toString() : null;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return array_values(array_map('strval', $this->array('measured_capabilities')));
    }
}
