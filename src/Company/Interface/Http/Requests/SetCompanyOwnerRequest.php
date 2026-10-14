<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetCompanyOwnerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'min:1']];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
