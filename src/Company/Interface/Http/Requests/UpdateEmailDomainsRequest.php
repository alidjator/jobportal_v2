<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Application\Team\InvitationEmailDomains;

final class UpdateEmailDomainsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'domains' => ['present', 'array', 'max:'.InvitationEmailDomains::MAX_DOMAINS],
            'domains.*' => ['string', 'max:253'],
        ];
    }

    /** @return list<string> */
    public function domains(): array
    {
        return array_values(array_map('strval', $this->array('domains')));
    }
}
