<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The question to open, by its index in the open section (from 0). */
final class MoveQuestionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['index' => ['required', 'integer', 'min:0', 'max:5000']];
    }

    public function index(): int
    {
        return $this->integer('index');
    }
}
