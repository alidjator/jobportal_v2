<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookmarkCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:255']];
    }
}
