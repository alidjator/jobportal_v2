<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Messaging\Application\MessageInput;

/** Who to write to, by exactly one of the account id, the slug of a candidate the search finds or the id of an application of the company, and the first message. */
final class StartCompanyConversationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required_without_all:candidate,application_id', 'prohibits:candidate,application_id', 'nullable', 'integer', 'min:1'],
            'candidate' => ['required_without_all:user_id,application_id', 'prohibits:user_id,application_id', 'nullable', 'string', 'max:40'],
            'application_id' => ['required_without_all:user_id,candidate', 'prohibits:user_id,candidate', 'nullable', 'integer', 'min:1'],
            ...MessageFields::rules(),
        ];
    }

    public function jobSeekerUserId(): ?int
    {
        return $this->filled('user_id') ? $this->integer('user_id') : null;
    }

    public function candidate(): ?string
    {
        return $this->filled('candidate') ? $this->string('candidate')->toString() : null;
    }

    public function applicationId(): ?int
    {
        return $this->filled('application_id') ? $this->integer('application_id') : null;
    }

    public function toInput(): MessageInput
    {
        return MessageFields::input($this);
    }
}
