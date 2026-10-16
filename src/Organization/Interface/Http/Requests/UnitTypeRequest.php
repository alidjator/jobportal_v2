<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A unit type: a new one takes `code` (letters, digits, dot, dash, underscore; up to 20), `name` and `level_order`; an edit takes `name` and `level_order`, each only when sent. */
final class UnitTypeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [...($this->isMethod('POST') ? ['required'] : ['prohibited']), 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => [$presence, 'string', 'min:2', 'max:100'],
            'level_order' => [$presence, 'integer', 'min:1', 'max:9999'],
        ];
    }

    public function code(): string
    {
        return trim($this->string('code')->toString());
    }

    public function name(): ?string
    {
        return $this->filled('name') ? trim($this->string('name')->toString()) : null;
    }

    public function levelOrder(): ?int
    {
        return $this->filled('level_order') ? $this->integer('level_order') : null;
    }
}
