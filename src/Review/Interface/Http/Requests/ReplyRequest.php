<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplyRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:10', 'max:1500']];
    }

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }
}
