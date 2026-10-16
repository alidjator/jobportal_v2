<?php

namespace Src\Approval\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The account that takes a vacant approver place: `user_id`. */
final class ReplaceApproverRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'min:1']];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
