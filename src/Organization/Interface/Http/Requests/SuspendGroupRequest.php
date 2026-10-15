<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The reason of a group suspension: required, 5 to 500 characters (audited; only platform admins read it). */
final class SuspendGroupRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
