<?php

namespace Src\Approval\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** A delegation: `delegate_user_id`, and the days `from` and `to` (both included, `YYYY-MM-DD`). */
final class DelegationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'delegate_user_id' => ['required', 'integer', 'min:1'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function delegateUserId(): int
    {
        return $this->integer('delegate_user_id');
    }

    public function from(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string('from')->toString());
    }

    public function to(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string('to')->toString());
    }
}
