<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Support\GrantSupportAccess;

/** Support access for one platform admin: `admin_id` (from the list of admins that may receive it) and `hours` (1 to 72). */
final class GrantSupportAccessRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['admin_id' => ['required', 'integer', 'min:1'], 'hours' => ['required', 'integer', 'min:1', 'max:'.GrantSupportAccess::MAX_HOURS]];
    }

    public function adminId(): int
    {
        return $this->integer('admin_id');
    }

    public function hours(): int
    {
        return $this->integer('hours');
    }
}
