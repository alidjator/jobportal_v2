<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `source=legacy` is the only kind a job seeker may remove (the lists carried over from karir.pkp). */
final class RemoveMyMembershipsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'in:legacy'],
        ];
    }
}
