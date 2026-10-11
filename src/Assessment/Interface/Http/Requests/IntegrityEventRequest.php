<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\IntegrityEventType;

/** The signal the browser noticed: `tab_switch`, `fullscreen_exit` or `paste`. */
final class IntegrityEventRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['type' => ['required', 'string', Rule::enum(IntegrityEventType::class)]];
    }

    public function type(): IntegrityEventType
    {
        return IntegrityEventType::from($this->string('type')->toString());
    }
}
