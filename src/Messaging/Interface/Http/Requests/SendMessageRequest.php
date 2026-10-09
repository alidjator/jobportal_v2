<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Messaging\Application\MessageInput;

/** A multipart message: `body` and `files[]`. */
final class SendMessageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return MessageFields::rules();
    }

    public function toInput(): MessageInput
    {
        return MessageFields::input($this);
    }
}
