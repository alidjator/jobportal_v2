<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Domain\UserType;

/** `audience` is `job_seeker` or `company_member` (the company owners). */
final class ActivationReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'audience' => ['required', 'string', 'in:job_seeker,company_member'],
        ];
    }

    public function audience(): UserType
    {
        return UserType::from($this->string('audience')->toString());
    }
}
