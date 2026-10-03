<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
