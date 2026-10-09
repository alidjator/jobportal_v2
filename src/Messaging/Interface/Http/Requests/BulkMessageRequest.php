<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Messaging\Application\MessageInput;

/** `application_ids` (distinct, of one job) and the message. */
final class BulkMessageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            ...MessageFields::rules(),
        ];
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return array_map('intval', array_values((array) $this->validated('application_ids')));
    }

    public function toInput(): MessageInput
    {
        return MessageFields::input($this);
    }
}
