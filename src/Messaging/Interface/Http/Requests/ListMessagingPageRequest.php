<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/** `cursor` and `per_page` of a conversation or message list. */
final class ListMessagingPageRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->cursorRules();
    }
}
