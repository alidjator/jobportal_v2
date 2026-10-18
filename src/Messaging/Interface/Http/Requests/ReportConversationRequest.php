<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Messaging\Domain\ReportReason;

/** The reason, an optional note (required for `other`, at most 1,000 characters) and the message the report is about, if any. */
final class ReportConversationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string'],
            'message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }

    public function messageId(): ?int
    {
        return $this->filled('message_id') ? $this->integer('message_id') : null;
    }
}
