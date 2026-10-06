<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RenameStageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:1', 'max:60']];
    }

    public function name(): string
    {
        return (string) $this->string('name');
    }
}
