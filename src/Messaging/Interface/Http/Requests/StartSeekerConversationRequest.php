<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Messaging\Application\MessageInput;

/** The company's id and the first message. */
final class StartSeekerConversationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], ...MessageFields::rules()];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }

    public function toInput(): MessageInput
    {
        return MessageFields::input($this);
    }
}
