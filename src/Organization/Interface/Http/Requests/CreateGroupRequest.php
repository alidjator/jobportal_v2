<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Group\LegalEntityInput;

/** A new group: its `name` (2 to 150 characters) and its first legal entity, `legal_entity`, usually the holding. */
final class CreateGroupRequest extends FormRequest
{
    use LegalEntityFields;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:150'], 'legal_entity' => ['required', 'array'], ...$this->legalEntityRules('legal_entity.')];
    }

    public function groupName(): string
    {
        return trim($this->string('name')->toString());
    }

    public function firstLegalEntity(): LegalEntityInput
    {
        return $this->legalEntityInput('legal_entity.');
    }
}
