<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Messaging\Application\MessageInput;

/** The job seeker's account id and the first message. */
final class StartCompanyConversationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'min:1'], ...MessageFields::rules()];
    }

    public function jobSeekerUserId(): int
    {
        return $this->integer('user_id');
    }

    public function toInput(): MessageInput
    {
        return MessageFields::input($this);
    }
}
