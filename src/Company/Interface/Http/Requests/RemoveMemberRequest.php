<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RemoveMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['transfer_to_user_id' => ['nullable', 'integer', 'min:1']];
    }

    public function transferToUserId(): ?int
    {
        return $this->filled('transfer_to_user_id') ? $this->integer('transfer_to_user_id') : null;
    }
}
