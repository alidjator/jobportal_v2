<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The new display name. Its length and characters are checked by the use case. */
final class RenameMyResumeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:1000']];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
