<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The battery to grant to a company. */
final class GrantBatteryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['test_id' => ['required', 'integer', 'min:1']];
    }

    public function testId(): int
    {
        return $this->integer('test_id');
    }
}
